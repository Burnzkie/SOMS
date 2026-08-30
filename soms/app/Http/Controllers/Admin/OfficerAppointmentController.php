<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfficerAppointmentController extends Controller {

    protected array $positions = [
        'President',
        'Vice President',
        'Secretary',
        'Treasurer',
        'Auditor',
        'Public Relations Officer',
    ];

    public function index(){

    $academicYear = now()->month >=6 ? now()->year . '-' . (now()->year + 1) : (now()-> year - 1) . '-' . now()->year;

    $activeByPosition = OfficerPosition::where('academic_year', $academicYear) ->where('is_active', true) ->with('user') ->get() ->keyBy('position_title');
    $panel = collect($this->positions)->map(fn ($position) => [
        'position' => $position,
        'position_id' => $activeByPosition->get($position)?->id,
        'officer' => $activeByPosition->get($position)?->user,
        'vacant' => !$activeByPosition->has($position),
    ]);

    $approvedStudents = User::where('role', 'student')->where('is_approved', true) ->orderBy('name')->get();

    return view('admin.officers', [
        'panel' => $panel,
        'approvedStudents' => $approvedStudents,
        'academicYear' => $academicYear,
    ]);
 }

        public function store(Request $request){

        $this->authorize('appoint', OfficerPosition::class);

                $request->validate([
                    'user_id' => 'required|exists:users,id',
                    'position_title' => 'required|in:' . implode(',', $this->positions),
                    'academic_year' => 'required|string',
                ]);
                $user = User::where('id', $request->user_id)->where('is_approved', true)->firstOrFail();
                    $org = Organization::first();

                    abort_if(
                        $org === null,
                        500,
                        'No organization record exists yet. Run: php artisan db:seed --class=AdminSeeder (or DevSeeder locally) to create one before appointing officers.'
                    );
                    
                    abort_if(
                        OfficerPosition::where('position_title', $request->position_title) ->where('academic_year', $request->academic_year) ->where('is_active', true) ->exists(),
                        422,
                            'This position is already actively held for this academic year. Revoke the current officer first.'
                    );

                    DB::transaction(function () use ($user, $request, $org){
                        OfficerPosition::create([
                            'user_id' => $user->id,
                            'organization_id' => $org?->id,
                            'position_title' => $request->position_title,
                            'academic_year' => $request->academic_year,
                            'is_active' => true,
                            'appointed_at' => now(),
                            'appointed_by' => auth()->id(),
                        ]);
                        $user->forceFill(['role' => 'officer'])->save();
                    });

                    ActivityLog::record(auth()->id(), 'officer_appointed', OfficerPosition::class, null, [
                        'user_id' => $user->id,
                        'position'=> $request->position_title,
                        'academic_year' => $request->academic_year,
                    ]);

                    NotificationService::send($user->id, 'appointed_to_officer',['position' => $request->position_title]);

                    return back()->with('status', "{$user->name} appointed as {$request->position_title}.");
        }

        /**
         * Revoke an active officer back to student — web counterpart to
         * Api\Admin\OfficerAppointmentController::revoke(). Previously only
         * reachable via the API; no route or button existed on this side,
         * so an admin using the web dashboard alone had no way to remove
         * an officer once appointed. See 04-Officer-Permissions-Members.md,
         * Revocation & Term End.
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

            return back()->with('status', "{$position->user->name} revoked from {$position->position_title}.");
        }
}