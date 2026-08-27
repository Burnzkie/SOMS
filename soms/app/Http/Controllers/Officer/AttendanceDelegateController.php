<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceDelegate;
use App\Models\EventSession;
use App\Models\User;
use App\Support\OfficerPermission;
use Illuminate\Http\Request;

/**
 * Attendance delegate assignment — see 05-Attendance-Fines.md /
 * 11-Testing-Maintenance.md ("Delegate assignment: dashboard card ->
 * attendance access granted, scoped to session; reassignment is immediate
 * (no stale cached grants)").
 *
 * A delegate is any approved student or officer granted scan/override
 * access to one specific session, without being Executive/Administrative
 * tier themselves. AttendanceSessionPolicy already checks
 * attendance_delegates directly (no caching), so removing a row here
 * revokes access on the delegate's very next request — there is nothing
 * else to invalidate.
 *
 * Only Executive/Administrative officers (manage_attendance) may assign
 * or remove delegates — this mirrors the "Close Session" authorization
 * boundary in AttendanceSessionPolicy::close, since delegation is itself
 * an attendance-management action, not something a delegate can do to
 * another delegate.
 */
class AttendanceDelegateController extends Controller
{
    protected function authorizeManage(): void
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'manage_attendance'), 403);
    }

    /**
     * Assign a delegate to a session. Search is by student_id — same
     * lookup pattern as manual override, so officers use a field they
     * already type into daily.
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

        return back()->with('status', "{$user->name} can now scan/override attendance for this session.");
    }

    /**
     * Remove a delegate — access is revoked immediately on the next
     * request, since AttendanceSessionPolicy checks this table live
     * rather than caching the grant.
     */
    public function destroy(EventSession $session, AttendanceDelegate $delegate)
    {
        $this->authorizeManage();

        abort_unless($delegate->event_session_id === $session->id, 404);

        ActivityLog::record(auth()->id(), 'attendance_delegate_removed', EventSession::class, $session->id, [
            'delegate_user_id' => $delegate->user_id,
        ]);

        $delegate->delete();

        return back()->with('status', 'Delegate access removed for this session.');
    }
}
