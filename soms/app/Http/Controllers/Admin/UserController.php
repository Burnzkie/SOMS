<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller {
    public function index(Request $request){
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
            });
        }
        if ($role = $request->input('role')){
            $query->where('role', $role);
        }
        if ($department = $request->input('department')) {
            $query->where('department', $department);
        }
        if ($request->input('status') === 'pending') {
            $query->where('is_approved', false);
        } elseif ($request->input('status') === 'approved'){
            $query->where('is_approved', true);
        }

        $users = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        return view('admin.users', ['users' => $users, 'pendingApprovals' => User::where('is_approved', false)->count(),]);
    }

    public function activityLog(User $user)
    {
        $logs = ActivityLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.user-activity-log', [
            'targetUser' => $user,
            'logs' => $logs,
        ]);
    }

    /**
     * Approve a pending student — generates their QR token, adds them to
     * organization_members, notifies, and logs the action.
     * See 09-Admin-Dashboard.md, Pending Student Approvals.
     */
    public function approve(User $user)
    {
        abort_if($user->is_approved, 422, 'This user is already approved.');

        DB::transaction(function () use ($user) {
            $user->forceFill([
                'is_approved' => true,
                'approved_by' => auth()->id(),
                // QR is now a live rotating token (QrTokenService) — nothing to
                // generate/store here. This timestamp just records when the
                // student's QR access activated. See 05-Attendance-Fines.md Part B.
                'qr_generated_at' => now(),
            ])->save();

            $org = Organization::first();
            if ($org) {
                OrganizationMember::firstOrCreate(
                    ['organization_id' => $org->id, 'user_id' => $user->id],
                    ['joined_at' => now()]
                );
            }
        });

        ActivityLog::record(auth()->id(), 'student_approved', User::class, $user->id, [
            'student_id' => $user->student_id,
        ]);

        NotificationService::send($user->id, 'account_approved');

        return back()->with('status', "{$user->name} approved. QR code generated.");
    }

    /**
     * Reject a pending student — soft delete with a logged reason.
     * See 09-Admin-Dashboard.md, Pending Student Approvals.
     */
    public function reject(Request $request, User $user)
    {
        abort_if($user->is_approved, 422, 'Cannot reject an already-approved user.');

        $request->validate(['reason' => 'required|string|min:5']);

        ActivityLog::record(auth()->id(), 'student_rejected', User::class, $user->id, [
            'student_id' => $user->student_id,
            'reason'     => $request->input('reason'),
        ]);

        NotificationService::send($user->id, 'account_rejected');

        $user->delete();

        return back()->with('status', "{$user->name}'s registration was rejected.");
    }
}