<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mobile counterpart to Http\Controllers\Admin\UserController — same
 * approval/rejection logic, JSON responses. See 09-Admin-Dashboard.md
 * and 10-Mobile-Deployment.md (Admin nav: Users, Officer Appointment,
 * Reports, Logs).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
            });
        }
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }
        if ($department = $request->input('department')) {
            $query->where('department', $department);
        }
        if ($request->input('status') === 'pending') {
            $query->where('is_approved', false);
        } elseif ($request->input('status') === 'approved') {
            $query->where('is_approved', true);
        }

        $users = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function activityLog(User $user)
    {
        $logs = ActivityLog::where('user_id', $user->id)->orderByDesc('created_at')->paginate(15);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * Approve a pending student — identical logic to the web controller:
     * generates QR access (live rotating token, nothing stored), adds to
     * organization_members, notifies, logs.
     */
    public function approve(User $user)
    {
        abort_if($user->is_approved, 422, 'This user is already approved.');

        DB::transaction(function () use ($user) {
            $user->forceFill([
                'is_approved'      => true,
                'approved_by'      => auth()->id(),
                'qr_generated_at'  => now(),
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

        return response()->json(['success' => true, 'data' => $user->fresh(), 'message' => "{$user->name} approved. QR code generated."]);
    }

    public function reject(Request $request, User $user)
    {
        abort_if($user->is_approved, 422, 'Cannot reject an already-approved user.');

        $data = $request->validate(['reason' => 'required|string|min:5']);

        ActivityLog::record(auth()->id(), 'student_rejected', User::class, $user->id, [
            'student_id' => $user->student_id,
            'reason'     => $data['reason'],
        ]);

        NotificationService::send($user->id, 'account_rejected');

        $user->delete();

        return response()->json(['success' => true, 'message' => "{$user->name}'s registration was rejected."]);
    }
}
