<?php

namespace App\Http\Controllers\Api\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Fine;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class FineController extends Controller
{
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

    public function clear(Request $request, Fine $fine)
    {
        $this->authorize('clear', $fine);
        $data = $request->validate(['reason' => 'required|string|min:5']);

        $fine->forceFill(['status' => 'paid', 'cleared_by' => auth()->id(), 'cleared_at' => now(), 'reason' => $data['reason']])->save();

        ActivityLog::record(auth()->id(), 'fine_cleared', Fine::class, $fine->id, ['reason' => $data['reason']]);
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
}
