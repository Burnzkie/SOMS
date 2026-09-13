<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

/**
 * Centralized in-app + push notification dispatch.
 * See 08-Announcements-Calendar-Notifications.md.
 *
 * v4.0: removed all election/party/candidacy templates and the
 * assigned_scorer/assigned_delegate/player_approved/player_rejected
 * templates (Sports Fest removal). Renamed promoted_to_officer ->
 * appointed_to_officer.
 *
 * FCM push sending is stubbed for now — this project doesn't yet have
 * laravel-notification-channels/fcm wired in. In-app notification rows
 * (the `notifications` table + bell icon) work today; push delivery is
 * a follow-up once FCM credentials/config are in place.
 */
class NotificationService
{
    public static function send(int $userId, string $type, array $data = []): void
    {
        $templates = [
            'account_approved'       => ['Account Approved', 'Your SGO account has been approved. Welcome!'],
            'account_rejected'       => ['Account Rejected', 'Your account registration was rejected.'],
            'fine_issued'            => ['Fine Issued', 'A fine has been issued to your account.'],
            'fine_cleared'           => ['Fine Cleared', 'One of your fines has been cleared.'],
            'fine_waived'            => ['Fine Waived', 'One of your fines has been waived.'],
            'announcement_published' => ['New Announcement', 'A new announcement has been posted.'],
            'appointed_to_officer'   => ['Officer Appointment', 'You have been appointed as ' . ($data['position'] ?? 'your new position') . '.'],
            'officer_term_ended'     => ['Officer Term Ended', 'Your term as ' . ($data['position'] ?? 'officer') . ' has ended.'],
            'event_published'        => ['Event Posted', '"' . ($data['title'] ?? 'A campus event') . '" has been posted to the calendar.'],
            'event_rescheduled'      => ['Event Rescheduled', '"' . ($data['title'] ?? 'An event') . '" has been moved to ' . ($data['date'] ?? 'a new date') . '.'],
            'calendar_entry_added'   => ['Calendar Entry Added', '"' . ($data['title'] ?? 'A calendar entry') . '" was added on ' . ($data['date'] ?? 'the calendar') . '.'],
            'calendar_entry_rescheduled' => ['Calendar Entry Moved', '"' . ($data['title'] ?? 'A calendar entry') . '" was moved to ' . ($data['date'] ?? 'a new date') . '.'],
        ];

        if (!isset($templates[$type])) {
            return;
        }

        [$title, $body] = $templates[$type];

        Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);

        $user = User::find($userId);
        if ($user?->fcm_token) {
            // FCM push send — wired in once laravel-notification-channels/fcm
            // and FCM_SERVER_KEY are configured (see 10-Mobile-Deployment.md).
        }
    }

    /**
     * Send the same notification to every member of an organization —
     * same "look up OrganizationMember rows, loop, send" pattern already
     * used by AnnouncementController::publish(). Used by calendar/event
     * scheduling so all users are notified when something is posted or
     * its date changes, not just the officer who made the change.
     */
    public static function broadcastToOrganization(?int $organizationId, string $type, array $data = []): void
    {
        if ($organizationId === null) {
            return;
        }

        $memberIds = \App\Models\OrganizationMember::where('organization_id', $organizationId)->pluck('user_id');

        foreach ($memberIds as $userId) {
            self::send($userId, $type, $data);
        }
    }
}
