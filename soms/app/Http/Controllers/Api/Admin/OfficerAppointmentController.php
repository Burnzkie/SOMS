<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mobile counterpart to Http\Controllers\Admin\OfficerAppointmentController.
 * See 04-Officer-Permissions-Members.md, Officer Appointment.
 */
class OfficerAppointmentController extends Controller
{
    protected array $positions = [
        'President',
        'Vice President',
        'Secretary',
        'Treasurer',
        'Auditor',
        'Public Relations Officer',
    ];

    /**
     * Panel state: current active officers per position (with "vacant"
     * flag) for the current academic year, plus the approved-student
     * pool to appoint from.
     */
    public function index()
    {
        $academicYear = now()->month >= 6
            ? now()->year . '-' . (now()->year + 1)
            : (now()->year - 1) . '-' . now()->year;

        $activeByPosition = OfficerPosition::where('academic_year', $academicYear)
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->keyBy('position_title');

        $panel = collect($this->positions)->map(fn ($position) => [
            'position' => $position,
            'officer'  => $activeByPosition->get($position)?->user,
            'vacant'   => !$activeByPosition->has($position),
        ]);

        $approvedStudents = User::where('role', 'student')->where('is_approved', true)->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => [
            'panel'            => $panel,
            'approvedStudents' => $approvedStudents,
            'academicYear'     => $academicYear,
        ]]);
    }

    public function store(Request $request)
    {
        $this->authorize('appoint', OfficerPosition::class);

        $data = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'position_title'  => 'required|in:' . implode(',', $this->positions),
            'academic_year'   => 'required|string',
        ]);

        $user = User::where('id', $data['user_id'])->where('is_approved', true)->firstOrFail();
        $org = Organization::first();

        abort_if(
            OfficerPosition::where('position_title', $data['position_title'])
                ->where('academic_year', $data['academic_year'])
                ->where('is_active', true)
                ->exists(),
            422,
            'This position is already actively held for this academic year. Revoke the current officer first.'
        );

        DB::transaction(function () use ($user, $data, $org) {
            OfficerPosition::create([
                'user_id'         => $user->id,
                'organization_id' => $org?->id,
                'position_title'  => $data['position_title'],
                'academic_year'   => $data['academic_year'],
                'is_active'       => true,
                'appointed_at'    => now(),
                'appointed_by'    => auth()->id(),
            ]);
            $user->forceFill(['role' => 'officer'])->save();
        });

        ActivityLog::record(auth()->id(), 'officer_appointed', OfficerPosition::class, null, [
            'user_id'       => $user->id,
            'position'      => $data['position_title'],
            'academic_year' => $data['academic_year'],
        ]);

        NotificationService::send($user->id, 'appointed_to_officer', ['position' => $data['position_title']]);

        return response()->json(['success' => true, 'message' => "{$user->name} appointed as {$data['position_title']}."]);
    }

    /**
     * Revoke an active officer -- see 04-Officer-Permissions-Members.md,
     * Revocation & Term End. Not exposed on the web Admin controller
     * either (currently only reachable via OfficerPositionPolicy::revoke
     * where a route calls it); included here since the mobile "Officer
     * Appointment" screen needs a revoke action per 10-Mobile-Deployment.md.
     */
    public function revoke(OfficerPosition $position)
    {
        $this->authorize('revoke', $position);

        abort_unless($position->is_active, 422, 'This officer position is not currently active.');

        $position->update(['is_active' => false]);
        $position->user->forceFill(['role' => 'student'])->save();

        ActivityLog::record(auth()->id(), 'officer_revoked', OfficerPosition::class, $position->id, [
            'user_id'  => $position->user_id,
            'position' => $position->position_title,
        ]);

        NotificationService::send($position->user_id, 'officer_term_ended', ['position' => $position->position_title]);

        return response()->json(['success' => true, 'message' => 'Officer revoked.']);
    }
}
