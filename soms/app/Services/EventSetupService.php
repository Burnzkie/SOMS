<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventDay;
use App\Models\EventFineRule;
use App\Models\EventSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared event-creation logic — used by both Officer\EventController
 * (web quick-create modal + full create form) and Api\Officer\EventController
 * (Flutter quick-create sheet), so the day/session/fine-rule generation
 * rules can't drift between the two entry points.
 *
 * Pulled out of Officer\EventController::store() when mobile event
 * creation was added — no behavior change from the original web-only
 * implementation.
 */
class EventSetupService
{
    /**
     * @param  array{title:string,description:?string,venue:?string,type:string,image_path:?string,color:?string,date_start:string,date_end:string,has_parade:bool}  $data
     */
    public static function create(array $data, int $organizationId, int $createdById): Event
    {
        return DB::transaction(function () use ($data, $organizationId, $createdById) {
            $event = Event::create([
                'organization_id' => $organizationId,
                'created_by'      => $createdById,
                'title'           => $data['title'],
                'description'     => $data['description'] ?? null,
                'venue'           => $data['venue'] ?? null,
                'type'            => $data['type'],
                'image_path'      => $data['image_path'] ?? null,
                'color'           => $data['color'] ?? '#FF7A29',
                'date_start'      => $data['date_start'],
                'date_end'        => $data['date_end'],
                'has_parade'      => (bool) ($data['has_parade'] ?? false),
            ]);

            $period = Carbon::parse($data['date_start'])->toPeriod(Carbon::parse($data['date_end']));

            foreach ($period as $date) {
                $day = EventDay::create([
                    'event_id' => $event->id,
                    'date'     => $date->toDateString(),
                ]);

                self::createDefaultSessions($day, $event, $date);
            }

            // Default fine rule amounts (₱0) so the table exists — officer edits amounts next.
            $violations = ['missed_morning_timein', 'missed_morning_timeout', 'missed_afternoon_timein', 'missed_afternoon_timeout'];
            if ($event->has_parade) {
                $violations[] = 'missed_parade';
            }
            foreach ($violations as $violation) {
                EventFineRule::firstOrCreate(
                    ['event_id' => $event->id, 'violation_type' => $violation],
                    ['amount' => 0]
                );
            }

            return $event;
        });
    }

    protected static function createDefaultSessions(EventDay $day, Event $event, Carbon $date): void
    {
        EventSession::create([
            'event_day_id'  => $day->id,
            'session_type'  => 'morning',
            'timein_start'  => $date->copy()->setTime(7, 0),
            'timein_end'    => $date->copy()->setTime(8, 0),
            'timeout_start' => $date->copy()->setTime(11, 30),
            'timeout_end'   => $date->copy()->setTime(12, 30),
        ]);

        EventSession::create([
            'event_day_id'  => $day->id,
            'session_type'  => 'afternoon',
            'timein_start'  => $date->copy()->setTime(13, 0),
            'timein_end'    => $date->copy()->setTime(14, 0),
            'timeout_start' => $date->copy()->setTime(17, 0),
            'timeout_end'   => $date->copy()->setTime(18, 0),
        ]);

        if ($event->has_parade) {
            EventSession::create([
                'event_day_id' => $day->id,
                'session_type' => 'parade',
                'timein_start' => $date->copy()->setTime(6, 0),
                'timein_end'   => $date->copy()->setTime(6, 45),
            ]);
        }
    }

    /**
     * Shifts the event's whole date range (and every generated EventDay +
     * its sessions) by the same number of days, preserving the event's
     * original duration and each session's time-of-day. Used by both the
     * web calendar's drag-to-reschedule and the mobile "change start
     * date" action, so they can't drift apart.
     *
     * Caller is responsible for the attendance-already-recorded guard —
     * this method only does the date math, since the two callers return
     * different response shapes (redirect vs JSON) on that rejection.
     *
     * @return int the delta in days actually applied (0 if unchanged)
     */
    public static function reschedule(Event $event, string $newStartDate): int
    {
        $newStart = Carbon::parse($newStartDate)->startOfDay();
        $deltaDays = $event->date_start->copy()->startOfDay()->diffInDays($newStart, false);

        if ($deltaDays === 0) {
            return 0;
        }

        DB::transaction(function () use ($event, $deltaDays) {
            $event->update([
                'date_start' => $event->date_start->copy()->addDays($deltaDays),
                'date_end'   => $event->date_end->copy()->addDays($deltaDays),
            ]);

            foreach ($event->eventDays()->with('sessions')->get() as $day) {
                $day->update(['date' => $day->date->copy()->addDays($deltaDays)]);

                foreach ($day->sessions as $session) {
                    $session->update([
                        'timein_start'  => $session->timein_start?->copy()->addDays($deltaDays),
                        'timein_end'    => $session->timein_end?->copy()->addDays($deltaDays),
                        'timeout_start' => $session->timeout_start?->copy()->addDays($deltaDays),
                        'timeout_end'   => $session->timeout_end?->copy()->addDays($deltaDays),
                    ]);
                }
            }
        });

        return $deltaDays;
    }
}
