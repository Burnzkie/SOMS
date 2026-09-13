<?php
// app/Logging/TapWithRedaction.php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Roadmap Phase 1.2 — Laravel "tap" class, wired via the 'tap' key on a
 * logging channel in config/logging.php. Pushes RedactSensitiveDataProcessor
 * onto every handler on the channel.
 */
class TapWithRedaction
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            $handler->pushProcessor(new RedactSensitiveDataProcessor());
        }
    }
}
