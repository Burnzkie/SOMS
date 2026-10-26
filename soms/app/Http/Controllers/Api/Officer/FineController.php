<?php
// app/Http/Controllers/Api/Officer/FineController.php

namespace App\Http\Controllers\Api\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Fine;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\PaymentReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FineController extends Controller
{
    /**
     * Per-fine list (unchanged) — kept as-is so the existing Flutter screens keep working.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Fine::class);

        $query = Fine::with(['user:id,name,student_id', 'event:id,title', 'eventSession:id,session_type']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $fines = $query->orderByDesc('issued_at')->paginate(20);

        return response()->json(['success' => true, 'data' => $fines]);
    }

    /**
     * NEW — one entry per student with overall totals and their fines nested inside,
     * so the treasurer can settle everything a student owes in one tap.
     * Query params: status (unpaid|paid|waived), event_id.
     */
    public function students(Request $request)
    {
        $this->authorize('viewAny', Fine::class);

        $status  = $request->input('status');
        $eventId = $request->input('event_id');

        // One row per student: unpaid total + counts per status
        $summaries = Fine::query()
            ->select('user_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'unpaid' THEN amount END), 0) AS unpaid_total")
            ->selectRaw("SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) AS unpaid_count")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_count")
            ->selectRaw("SUM(CASE WHEN status = 'waived' THEN 1 ELSE 0 END) AS waived_count")
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->groupBy('user_id')
            // Whitelisted before interpolation, so safe
            ->when(in_array($status, ['unpaid', 'paid', 'waived'], true),
                fn ($q) => $q->having("{$status}_count", '>', 0))
            ->orderByDesc('unpaid_total')
            ->orderBy('user_id') // stable pagination order
            ->paginate(20)
            ->withQueryString();

        $userIds = $summaries->getCollection()->pluck('user_id');

        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'student_id'])->keyBy('id');

        $finesByUser = Fine::with(['event:id,title', 'eventSession:id,session_type'])
            ->whereIn('user_id', $userIds)
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->orderByDesc('issued_at')
            ->get()
            ->groupBy('user_id');

        // Reshape each row into the JSON the app consumes
        $summaries->through(fn ($row) => [
            'user_id'      => $row->user_id,
            'name'         => $users[$row->user_id]->name,
            'student_id'   => $users[$row->user_id]->student_id,
            'unpaid_total' => number_format((float) $row->unpaid_total, 2, '.', ''),
            'unpaid_count' => (int) $row->unpaid_count,
            'paid_count'   => (int) $row->paid_count,
            'waived_count' => (int) $row->waived_count,
            'fines'        => ($finesByUser[$row->user_id] ?? collect())->values(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $summaries,
            'meta'    => ['grand_unpaid_total' => number_format((float) Fine::unpaid()->sum('amount'), 2, '.', '')],
        ]);
    }

    public function clear(Request $request, Fine $fine)
    {
        $this->authorize('clear', $fine);
        $data = $request->validate([
            'reason'         => 'required|string|min:5',
            'receipt_number' => PaymentReceipt::rules(),
        ]);
        $receipt = PaymentReceipt::clean($data['receipt_number'] ?? null);

        $fine->forceFill(['status' => 'paid', 'cleared_by' => auth()->id(), 'cleared_at' => now(), 'reason' => $data['reason'], 'receipt_number' => $receipt])->save();

        ActivityLog::record(auth()->id(), 'fine_cleared', Fine::class, $fine->id, ['reason' => $data['reason'], 'receipt_number' => $receipt]);
        NotificationService::send($fine->user_id, 'fine_cleared', ['fine_id' => $fine->id]);

        return response()->json(['success' => true, 'data' => $fine]);
    }

    public function waive(Request $request, Fine $fine)
    {
        $this->authorize('waive', $fine);
        $data = $request->validate(['reason' => 'required|string|min:5']);
        abort_if($fine->status === 'paid', 422, 'Cannot waive an already-paid fine.');

        $fine->forceFill(['status' => 'waived', 'cleared_by' => auth()->id(), 'cleared_at' => now(), 'reason' => $data['reason']])->save();

        ActivityLog::record(auth()->id(), 'fine_waived', Fine::class, $fine->id, ['reason' => $data['reason']]);
        NotificationService::send($fine->user_id, 'fine_waived', ['fine_id' => $fine->id]);

        return response()->json(['success' => true, 'data' => $fine]);
    }

    /**
     * NEW — mark ALL of a student's unpaid fines as paid.
     */
    public function clearAll(Request $request, User $student)
    {
        return $this->settleAllUnpaid($request, $student, 'paid', 'clear', 'fine_cleared');
    }

    /**
     * NEW — waive ALL of a student's unpaid fines.
     */
    public function waiveAll(Request $request, User $student)
    {
        return $this->settleAllUnpaid($request, $student, 'waived', 'waive', 'fine_waived');
    }

    /**
     * Shared bulk logic: settles every unpaid fine of one student in one transaction
     * (all-or-nothing). Only 'unpaid' rows are touched, so paid fines can never be waived.
     */
    private function settleAllUnpaid(Request $request, User $student, string $status, string $ability, string $eventType)
    {
        $data = $request->validate([
            'reason'         => 'required|string|min:5|max:255',
            // A receipt only applies to payments, never to waivers.
            'receipt_number' => $status === 'paid' ? PaymentReceipt::rules() : 'prohibited',
        ]);
        $receipt = $status === 'paid' ? PaymentReceipt::clean($data['receipt_number'] ?? null) : null;

        $settled = DB::transaction(function () use ($student, $status, $ability, $eventType, $data, $receipt) {
            // lockForUpdate stops a double-tap / second officer from settling the same rows twice
            $fines = Fine::where('user_id', $student->id)->unpaid()->lockForUpdate()->get();

            foreach ($fines as $fine) {
                $this->authorize($ability, $fine); // reuse FinePolicy; a 403 rolls everything back

                $fine->forceFill([
                    'status'     => $status,
                    'cleared_by' => auth()->id(),
                    'cleared_at' => now(),
                    'reason'     => $data['reason'],
                    'receipt_number' => $receipt,
                ])->save();

                ActivityLog::record(auth()->id(), $eventType, Fine::class, $fine->id, [
                    'reason' => $data['reason'], 'student_id' => $student->id, 'bulk' => true, 'receipt_number' => $receipt,
                ]);
            }

            return $fines;
        });

        // Notify after commit, same payload shape as the single-fine endpoints
        foreach ($settled as $fine) {
            NotificationService::send($student->id, $eventType, ['fine_id' => $fine->id]);
        }

        return response()->json([
            'success' => true,
            'message' => $settled->isEmpty()
                ? 'No unpaid fines for this student.'
                : $settled->count() . ' fine(s) ' . ($status === 'paid' ? 'marked paid.' : 'waived.'),
            'data'    => [
                'settled_count' => $settled->count(),
                'settled_total' => number_format((float) $settled->sum('amount'), 2, '.', ''),
            ],
        ]);
    }
}