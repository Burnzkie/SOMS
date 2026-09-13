<?php
// app/Http/Controllers/SearchController.php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Fine;
use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\User;
use App\Support\OfficerPermission;
use Illuminate\Http\Request;

/**
 * Backs the header search box shared by every role's layout (see
 * components/layout.blade.php). What gets searched depends on the
 * signed-in user's role, mirroring what each role can already see
 * elsewhere in the app — this never surfaces data a role couldn't
 * already reach through its own nav links:
 *   - Officer: Events, Announcements, and (Treasurer only) Fines
 *   - Student: Events, Announcements, and their own Fines
 *   - Admin: Users, Officer appointments, Activity logs
 */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();

        $events = collect();
        $announcements = collect();
        $fines = collect();
        $users = collect();
        $officerPositions = collect();
        $activityLogs = collect();

        if ($q !== '') {
            if (in_array($user->role, ['officer', 'student'], true)) {
                $orgId = $user->activeOfficerPosition?->organization_id
                    ?? Organization::query()->value('id');

                $events = Event::where('organization_id', $orgId)
                    ->where(function ($query) use ($q) {
                        $query->where('title', 'like', "%{$q}%")
                              ->orWhere('venue', 'like', "%{$q}%");
                    })
                    ->orderByDesc('date_start')
                    ->limit(15)
                    ->get();

                $announcements = Announcement::where('organization_id', $orgId)
                    ->where('is_published', true)
                    ->where(function ($query) use ($q) {
                        $query->where('title', 'like', "%{$q}%")
                              ->orWhere('body', 'like', "%{$q}%");
                    })
                    ->latest()
                    ->limit(15)
                    ->get();

                if ($user->role === 'officer' && OfficerPermission::isTreasurer($user)) {
                    // Treasurer sees fines across the whole org — same
                    // scope Officer\FineController::index already grants.
                    $fines = Fine::whereHas('event', fn ($q2) => $q2->where('organization_id', $orgId))
                        ->where(function ($query) use ($q) {
                            $query->where('violation_type', 'like', "%{$q}%")
                                  ->orWhere('reason', 'like', "%{$q}%")
                                  ->orWhereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$q}%")
                                                                      ->orWhere('student_id', 'like', "%{$q}%"))
                                  ->orWhereHas('event', fn ($q2) => $q2->where('title', 'like', "%{$q}%"));
                        })
                        ->with(['user', 'event'])
                        ->latest('issued_at')
                        ->limit(15)
                        ->get();
                } elseif ($user->role === 'student') {
                    // Students only ever see their own fines — matches
                    // Student\FineController::index's scope.
                    $fines = Fine::where('user_id', $user->id)
                        ->where(function ($query) use ($q) {
                            $query->where('violation_type', 'like', "%{$q}%")
                                  ->orWhere('reason', 'like', "%{$q}%")
                                  ->orWhereHas('event', fn ($q2) => $q2->where('title', 'like', "%{$q}%"));
                        })
                        ->with('event')
                        ->latest('issued_at')
                        ->limit(15)
                        ->get();
                }
            }

            if ($user->role === 'admin') {
                $users = User::where(function ($query) use ($q) {
                        $query->where('name', 'like', "%{$q}%")
                              ->orWhere('student_id', 'like', "%{$q}%");
                    })
                    ->limit(20)
                    ->get();

                $officerPositions = OfficerPosition::where('position_title', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$q}%")
                                                         ->orWhere('student_id', 'like', "%{$q}%"))
                    ->with('user')
                    ->latest('appointed_at')
                    ->limit(15)
                    ->get();

                $activityLogs = ActivityLog::where('action', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$q}%"))
                    ->with('user')
                    ->latest()
                    ->limit(15)
                    ->get();
            }
        }

        return view('search.index', [
            'q' => $q,
            'events' => $events,
            'announcements' => $announcements,
            'fines' => $fines,
            'users' => $users,
            'officerPositions' => $officerPositions,
            'activityLogs' => $activityLogs,
        ]);
    }
}
