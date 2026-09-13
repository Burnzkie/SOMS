<?php

namespace App\Http\Controllers\Api\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceDelegate;
use App\Models\EventSession;
use App\Models\User;
use App\Support\OfficerPermission;
use Illuminate\Http\Request;

/**
 * Mobile counterpart to Http\Controllers\Officer\AttendanceDelegateController.
 * See that class's docblock for the delegation model — unchanged here,
 * this only swaps `back()->with('status', ...)` redirects for JSON.
 */
class AttendanceDelegateController extends Controller
{
    protected function authorizeManage(): void
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'manage_attendance'), 403);
    }

    /**
     * GET /api/v1/officer/attendance/sessions/{session}/delegates
     */
    public function index(EventSession $session)
    {
        $this->authorizeManage();

        $delegates = AttendanceDelegate::where('event_session_id', $session->id)
            ->with('user')
            ->get();

        return response()->json(['success' => true, 'data' => $delegates]);
    }

    /**
     * POST /api/v1/officer/attendance/sessions/{session}/delegates
     */
    public function store(Request $request, EventSession $session)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'student_id' => 'required|exists:users,student_id',
        ]);

        $user = User::where('student_id', $data['student_id'])->firstOrFail();

        abort_if(
            AttendanceDelegate::where('event_session_id', $session->id)
                ->where('user_id', $user->id)
                ->exists(),
            422,
            'This user is already a delegate for this session.'
        );

        $delegate = AttendanceDelegate::create([
            'event_session_id' => $session->id,
            'user_id'          => $user->id,
            'assigned_by'      => auth()->id(),
        ]);

        ActivityLog::record(auth()->id(), 'attendance_delegate_assigned', EventSession::class, $session->id, [
            'delegate_user_id' => $user->id,
            'student_id'       => $user->student_id,
        ]);

        return response()->json(['success' => true, 'message' => "{$user->name} can now scan/override attendance for this session.", 'data' => $delegate->load('user')]);
    }

    /**
     * DELETE /api/v1/officer/attendance/sessions/{session}/delegates/{delegate}
     */
    public function destroy(EventSession $session, AttendanceDelegate $delegate)
    {
        $this->authorizeManage();

        abort_unless($delegate->event_session_id === $session->id, 404);

        ActivityLog::record(auth()->id(), 'attendance_delegate_removed', EventSession::class, $session->id, [
            'delegate_user_id' => $delegate->user_id,
        ]);

        $delegate->delete();

        return response()->json(['success' => true, 'message' => 'Delegate access removed for this session.']);
    }
}
