<?php

namespace App\Support;

/**
 * Single source of truth for the `events.type` enum — used by the
 * quick-create modal (web calendar dateClick) and the mobile quick-create
 * sheet, as well as the full /officer/events/create form and both
 * EventController::store() validation rules (web + API).
 *
 * Foundation Day is a multi-activity week rather than a single event, but
 * we deliberately did NOT add a separate `activity` column for it — the
 * FOUNDATION_DAY_ACTIVITIES list below is a client-side title assist only
 * (e.g. selecting "Parade" prefills the title as "Foundation Day —
 * Parade"). Keeps the schema flat; the activity itself just lives in the
 * event's title/description like any other event does.
 */
class EventTypes
{
    public const TYPES = [
        'student_assembly' => ['emoji' => '🎉', 'label' => 'Student Assembly'],
        'friendship_week' => ['emoji' => '❤️', 'label' => 'Friendship Week'],
        'community_service' => ['emoji' => '🌱', 'label' => 'Community Service'],
        'academic_activities' => ['emoji' => '🎓', 'label' => 'Academic Activities'],
        'talent_festival' => ['emoji' => '🎤', 'label' => 'Talent Festival'],
        'freshmen_welcome' => ['emoji' => '☀️', 'label' => 'Freshmen Welcome'],
        'week_of_prayer' => ['emoji' => '🙏', 'label' => 'Week of Prayer'],
        'intramurals' => ['emoji' => '🏆', 'label' => 'Intramurals'],
        'technology_week' => ['emoji' => '💻', 'label' => 'Technology Week'],
        'acquaintance_party' => ['emoji' => '🎭', 'label' => 'Acquaintance Party'],
        'foundation_day' => ['emoji' => '🎉', 'label' => 'Foundation Day'],
        'leadership_recognition' => ['emoji' => '🏅', 'label' => 'Leadership & Recognition'],
        'christmas_celebration' => ['emoji' => '🎄', 'label' => 'Christmas Celebration'],
        'other' => ['emoji' => '📌', 'label' => 'Other'],
    ];

    /** Sub-activities under a Foundation Day week — title assist only, see class docblock. */
    public const FOUNDATION_DAY_ACTIVITIES = [
        'Parade',
        'Booth Competition',
        'Sports',
        'Academic Competitions',
        'PAC Got Talent',
        'Amazing Race',
        'Mr. & Ms. Foundation Day',
        'Cultural Night',
        'Awarding Ceremony',
    ];

    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }

    /** For Laravel's `in:` validation rule. */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::keys());
    }

    /** [value => "emoji label"] — for a Blade <select> or JSON to Flutter. */
    public static function forSelect(): array
    {
        return array_map(fn ($t) => $t['emoji'] . ' ' . $t['label'], self::TYPES);
    }
}
