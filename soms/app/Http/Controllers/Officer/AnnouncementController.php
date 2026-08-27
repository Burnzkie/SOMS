<?php

namespace App\Http\Controllers\Officer;

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
 * Announcements — see 08-Announcements-Calendar-Notifications.md.
 * All officer tiers can draft; only Executive can publish/unpublish.
 */
class AnnouncementController extends Controller
{
    protected function organizationId(): ?int
    {
        return auth()->user()->activeOfficerPosition?->organization_id
            ?? Organization::query()->value('id');
    }

    public function index()
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'draft_announcements'), 403);

        $announcements = Announcement::where('organization_id', $this->organizationId())
            ->latest()
            ->paginate(15);

        $canPublish = OfficerPermission::can(auth()->user(), 'manage_announcements');

        return view('officer.announcements.index', compact('announcements', 'canPublish'));
    }

    public function create()
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'draft_announcements'), 403);

        return view('officer.announcements.create');
    }

    public function store(Request $request)
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'draft_announcements'), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string',
        ]);

        $announcement = Announcement::create([
            'organization_id' => $this->organizationId(),
            'created_by'      => auth()->id(),
            'title'           => $data['title'],
            'body'            => $data['body'],
            'is_published'    => false,
        ]);

        ActivityLog::record(auth()->id(), 'announcement_drafted', Announcement::class, $announcement->id);

        return redirect()->route('officer.announcements.index')->with('status', 'Draft saved. An Executive officer must publish it.');
    }

    public function publish(Announcement $announcement)
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'manage_announcements'), 403);
        abort_unless($announcement->organization_id === $this->organizationId(), 404);

        $announcement->update(['is_published' => true]);

        // Explicit cache invalidation on publish — see 03-Auth-Security.md §20.11.
        Cache::forget("public:announcements:org:{$announcement->organization_id}");

        ActivityLog::record(auth()->id(), 'announcement_published', Announcement::class, $announcement->id);

        $memberIds = OrganizationMember::where('organization_id', $announcement->organization_id)->pluck('user_id');
        foreach ($memberIds as $userId) {
            NotificationService::send($userId, 'announcement_published', ['announcement_id' => $announcement->id]);
        }

        return back()->with('status', 'Announcement published and members notified.');
    }

    public function unpublish(Announcement $announcement)
    {
        abort_unless(OfficerPermission::can(auth()->user(), 'manage_announcements'), 403);
        abort_unless($announcement->organization_id === $this->organizationId(), 404);

        $announcement->update(['is_published' => false]);
        Cache::forget("public:announcements:org:{$announcement->organization_id}");

        ActivityLog::record(auth()->id(), 'announcement_unpublished', Announcement::class, $announcement->id);

        return back()->with('status', 'Announcement unpublished.');
    }
}
