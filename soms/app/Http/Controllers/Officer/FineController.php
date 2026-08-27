<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Fine;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Treasurer + Admin fine management — see 05-Attendance-Fines.md Part D.
 * All actions gated by FinePolicy (Treasurer position or admin role only).
 */
class FineController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Fine::class);

        $query = Fine::with(['user:id,name,student_id', 'event:id,title', 'eventSession:id,session_type']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($eventId = $request->input('event_id')) {
            $query->where('event_id', $eventId);
        }

        $fines = $query->orderByDesc('issued_at')->paginate(20)->withQueryString();

        return view('officer.fines.index', compact('fines'));
    }

    /**
     * Mark paid after in-person payment.
     */
    public function clear(Request $request, Fine $fine)
    {
        $this->authorize('clear', $fine);

        $data = $request->validate(['reason' => 'required|string|min:5']);

        $fine->forceFill([
            'status'      => 'paid',
            'cleared_by'  => auth()->id(),
            'cleared_at'  => now(),
            'reason'      => $data['reason'],
        ])->save();

        ActivityLog::record(auth()->id(), 'fine_cleared', Fine::class, $fine->id, [
            'reason' => $data['reason'], 'student_id' => $fine->user_id,
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
}
