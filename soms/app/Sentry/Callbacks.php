<?php
// app/Sentry/Callbacks.php

namespace App\Sentry;

use Sentry\Event;
use Sentry\EventHint;

/**
 * Referenced from config/sentry.php as [Callbacks::class, 'beforeSend']
 * rather than an inline closure. This isn't a style choice: start.sh
 * runs `php artisan config:cache` on every deploy, and Laravel's config
 * cache serializes the whole config array to a single compiled PHP
 * file -- a Closure isn't serializable, so a closure here would throw
 * "Your configuration files are not serializable" and break every
 * production deploy. A static method reference is just a string/array,
 * which caches fine.
 */
class Callbacks
{
    public static function beforeSend(Event $event, ?EventHint $hint): ?Event
    {
        // CRITICAL for this project: SOMS handles student records under
        // RA 10173 (Data Privacy Act of 2012). Belt-and-suspenders
        // alongside send_default_pii=false in config/sentry.php: even
        // with PII collection off, strip cookies/headers if they ever
        // end up attached to an event by some other path (e.g. a
        // third-party package's own Sentry integration).
        $request = $event->getRequest();
        if ($request !== null) {
            foreach (['cookies', 'headers'] as $key) {
                if (isset($request[$key])) {
                    unset($request[$key]);
                }
            }
            $event->setRequest($request);
        }

        return $event;
    }
}
