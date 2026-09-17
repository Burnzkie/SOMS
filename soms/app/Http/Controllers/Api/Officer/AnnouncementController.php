<?php

namespace App\Http\Controllers\Api\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Services\NotificationService;
use App\Support\OfficerPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Mobile counterpart to Officer\AnnouncementController (the web version) —
 * same business logic and permission checks (all tiers can draft, only
 * Executive can publish/unpublish, per 08-Announcements-Calendar-
 * Notifications.md), just JSON responses shaped for
 * officerAnnouncementsProvider in the Flutter app instead of Blade views.
 *
 * This controller didn't exist before — routes/api_officer.php had
 * events/calendar/attendance/fines but no announcements endpoint, even
 * though the Flutter screens, providers, and model were already built
 * against it. That mismatch is what surfaced as "The route
 * api/v1/officer/announcements could not be found" on device.
 */
class AnnouncementController extends Controller
{
    protected function organizationId(Request $request): ?int
    {
        return $request->user()->activeOfficerPosition?->organization_id
            ?? Organization::query()->value('id');
    }

    public function index(Request $request)
    {
        abort_unless(OfficerPermission::can($request->user(), 'draft_announcements'), 403);

        $announcements = Announcement::where('organization_id', $this->organizationId($request))
            ->latest()
            ->paginate(15);

        $canPublish = OfficerPermission::can($request->user(), 'manage_announcements');

        // Nested under 'data' as {announcements, canPublish} — matches
        // officerAnnouncementsProvider's expected shape exactly (it reads
        // data['announcements'] and data['canPublish']), unlike the web
        // controller's separate view variables.
        return response()->json([
            'success' => true,
            'data' => [
                'announcements' => $announcements,
                'canPublish' => $canPublish,
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(OfficerPermission::can($request->user(), 'draft_announcements'), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string',
        ]);

        $announcement = Announcement::create([
            'organization_id' => $this->organizationId($request),
            'created_by'      => $request->user()->id,
            'title'           => $data['title'],
            'body'            => $data['body'],
            'is_published'    => false,
        ]);

        ActivityLog::record($request->user()->id, 'announcement_drafted', Announcement::class, $announcement->id);

        return response()->json(['success' => true, 'data' => $announcement], 201);
    }

    public function publish(Request $request, Announcement $announcement)
    {
        abort_unless(OfficerPermission::can($request->user(), 'manage_announcements'), 403);
        abort_unless($announcement->organization_id === $this->organizationId($request), 404);

        $announcement->update(['is_published' => true]);

        // Same cache key the public/student side reads — see
        // 03-Auth-Security.md §20.11. Without this, a newly published
        // announcement wouldn't show up for students for up to 30s.
        Cache::forget("public:announcements:org:{$announcement->organization_id}");

        ActivityLog::record($request->user()->id, 'announcement_published', Announcement::class, $announcement->id);

        $memberIds = OrganizationMember::where('organization_id', $announcement->organization_id)->pluck('user_id');
        foreach ($memberIds as $userId) {
            NotificationService::send($userId, 'announcement_published', ['announcement_id' => $announcement->id]);
        }

        return response()->json(['success' => true, 'data' => $announcement]);
    }

    public function unpublish(Request $request, Announcement $announcement)
    {
        abort_unless(OfficerPermission::can($request->user(), 'manage_announcements'), 403);
        abort_unless($announcement->organization_id === $this->organizationId($request), 404);

        $announcement->update(['is_published' => false]);
        Cache::forget("public:announcements:org:{$announcement->organization_id}");

        ActivityLog::record($request->user()->id, 'announcement_unpublished', Announcement::class, $announcement->id);

        return response()->json(['success' => true, 'data' => $announcement]);
    }
}
