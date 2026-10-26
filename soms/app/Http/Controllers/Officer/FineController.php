<?php
// app/Http/Controllers/Officer/FineController.php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Fine;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\PaymentReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Treasurer + Admin fine management — see 05-Attendance-Fines.md Part D.
 * All actions gated by FinePolicy (Treasurer position or admin role only).
 */
class FineController extends Controller
{
    /**
     * Fines overview: one row per student with their overall totals,
     * so the treasurer can settle everything a student owes in one click.
     */
    public function index(Request $request)
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
            // Status filter = "students with at least one fine in this status" (whitelisted, so safe to interpolate)
            ->when(in_array($status, ['unpaid', 'paid', 'waived'], true),
                fn ($q) => $q->having("{$status}_count", '>', 0))
            ->orderByDesc('unpaid_total')
            ->orderBy('user_id') // stable pagination order
            ->paginate(20)
            ->withQueryString();

        $userIds = $summaries->pluck('user_id');

        $users = User::whereIn('id', $userIds)
            ->get(['id', 'name', 'student_id'])
            ->keyBy('id');

        // Individual fines for the expandable breakdown (current page only)
        $finesByUser = Fine::with(['event:id,title', 'eventSession:id,session_type'])
            ->whereIn('user_id', $userIds)
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->orderByDesc('issued_at')
            ->get()
            ->groupBy('user_id');

        $grandUnpaid = Fine::unpaid()->sum('amount');

        return view('officer.fines.index', compact('summaries', 'users', 'finesByUser', 'grandUnpaid'));
    }

    /**
     * Mark paid after in-person payment.
     */
    public function clear(Request $request, Fine $fine)
    {
        $this->authorize('clear', $fine);

        $data = $request->validate([
            'reason'         => 'required|string|min:5',
            'receipt_number' => PaymentReceipt::rules(),
        ]);
        $receipt = PaymentReceipt::clean($data['receipt_number'] ?? null);

        $fine->forceFill([
            'status'         => 'paid',
            'cleared_by'     => auth()->id(),
            'cleared_at'     => now(),
            'reason'         => $data['reason'],
            'receipt_number' => $receipt,
        ])->save();

        ActivityLog::record(auth()->id(), 'fine_cleared', Fine::class, $fine->id, [
            'reason' => $data['reason'], 'student_id' => $fine->user_id, 'receipt_number' => $receipt,
        ]);
        NotificationService::send($fine->user_id, 'fine_cleared', ['fine_id' => $fine->id, 'reason' => $data['reason']]);

        return back()->with('status', 'Fine marked paid.');
    }

    /**
     * Waive — forgiven without payment. Distinct from clear(). See
     * 05-Attendance-Fines.md "Fine Waiver — distinct from clearing-after-payment".
     */
    public function waive(Request $request, Fine $fine)
    {
        $this->authorize('waive', $fine);

        $data = $request->validate(['reason' => 'required|string|min:5']);

        abort_if($fine->status === 'paid', 422, 'Cannot waive an already-paid fine.');

        $fine->forceFill([
            'status'     => 'waived',
            'cleared_by' => auth()->id(),
            'cleared_at' => now(),
            'reason'     => $data['reason'],
        ])->save();

        ActivityLog::record(auth()->id(), 'fine_waived', Fine::class, $fine->id, [
            'reason' => $data['reason'], 'student_id' => $fine->user_id,
        ]);
        NotificationService::send($fine->user_id, 'fine_waived', ['fine_id' => $fine->id, 'reason' => $data['reason']]);

        return back()->with('status', 'Fine waived.');
    }

    /**
     * Mark ALL of a student's unpaid fines as paid (in-person payment).
     */
    public function clearAll(Request $request, User $student)
    {
        return $this->settleAllUnpaid($request, $student, 'paid', 'clear', 'fine_cleared');
    }

    /**
     * Waive ALL of a student's unpaid fines.
     */
    public function waiveAll(Request $request, User $student)
    {
        return $this->settleAllUnpaid($request, $student, 'waived', 'waive', 'fine_waived');
    }

    /**
     * Shared bulk logic: settles every unpaid fine of one student in one transaction,
     * so it's all-or-nothing. Only touches 'unpaid' rows, so paid fines can never be waived.
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
            // lockForUpdate prevents a double-click / second officer from settling the same rows twice
            $fines = Fine::where('user_id', $student->id)->unpaid()->lockForUpdate()->get();

            foreach ($fines as $fine) {
                $this->authorize($ability, $fine); // reuse FinePolicy per fine; a 403 rolls everything back

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

        if ($settled->isEmpty()) {
            return back()->with('status', 'No unpaid fines for this student.');
        }

        // Notify after commit, same payload shape as the single-fine actions
        foreach ($settled as $fine) {
            NotificationService::send($student->id, $eventType, ['fine_id' => $fine->id, 'reason' => $data['reason']]);
        }

        return back()->with('status', $settled->count() . ' fine(s) ' . ($status === 'paid' ? 'marked paid.' : 'waived.'));
    }
}