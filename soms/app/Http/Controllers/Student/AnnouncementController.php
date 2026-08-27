<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Support\Facades\Cache;

class AnnouncementController extends Controller
{
    /**
     * Published announcements for the student's organization.
     * Short-TTL cached per 03-Auth-Security.md §20.11, invalidated
     * explicitly on publish (see Officer AnnouncementController, once built).
     */
    public function index()
    {
        $user = auth()->user();
        $orgIds = $user->organizationMemberships()->pluck('organization_id');
        $orgId = $orgIds->first();

        $announcements = $orgId
            ? Cache::remember("public:announcements:org:{$orgId}", 30, function () use ($orgId) {
                return Announcement::where('organization_id', $orgId)
                    ->where('is_published', true)
                    ->latest()
                    ->get();
            })
            : collect();

        return view('student.announcements.index', [
            'announcements' => $announcements,
        ]);
    }

    public function show(Announcement $announcement)
    {
        $user = auth()->user();
        $orgIds = $user->organizationMemberships()->pluck('organization_id');

        abort_unless($announcement->is_published && $orgIds->contains($announcement->organization_id), 404);

        return view('student.announcements.show', [
            'announcement' => $announcement,
        ]);
    }
}
