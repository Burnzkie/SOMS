<?php
// app/Logging/RedactSensitiveDataProcessor.php

namespace App\Logging;

use Monolog\LogRecord;

/**
 * Roadmap Phase 1.2 — scrubs secret-shaped strings out of log records before
 * they hit disk.
 *
 * Trigger: a Sanctum token fragment showed up in laravel.log inside a
 * PHP exception stack trace (getTraceAsString() includes scalar argument
 * values by default — e.g. PersonalAccessToken::findToken('<token>')).
 * That's normal PHP behavior, not a bug in this app's code, but it means
 * any function called with a token/password/secret as an argument will
 * leak it into logs the moment it throws from inside that call stack.
 *
 * This processor runs on every log record (message + context + any
 * formatted exception string Monolog produces) and redacts known
 * secret shapes. It's a safety net, not a substitute for not logging
 * secrets deliberately — see the codebase's existing rule against
 * logging credential-bearing request bodies.
 */
class RedactSensitiveDataProcessor
{
    /**
     * Pattern => replacement. Order matters: more specific patterns first.
     */
    private const PATTERNS = [
        // Sanctum plain-text tokens: "<id>|<40-char-random>"
        '/\b\d+\|[A-Za-z0-9]{40}\b/' => '[redacted-token]',
        // Bearer tokens in Authorization headers/log lines
        '/\bBearer\s+[A-Za-z0-9\-._~+\/]+=*/i' => 'Bearer [redacted-token]',
        // password=..., password: "...", 'password' => '...'
        '/([\'"]?password[\'"]?\s*(?:=>|=|:)\s*[\'"])([^\'"]+)([\'"])/i' => '$1[redacted]$3',
        // AWS/R2-style access keys
        '/\b(AKIA|ASIA)[A-Z0-9]{16}\b/' => '[redacted-access-key]',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $this->redact($record->message);
        $context = $this->redactArray($record->context);

        return $record->with(message: $message, context: $context);
    }

    private function redact(string $value): string
    {
        return preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $value) ?? $value;
    }

    private function redactArray(array $data): array
    {
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = $this->redact($value);
            }
        });

        return $data;
    }
}
