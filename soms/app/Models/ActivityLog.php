<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'payload',
        'ip_address',
        'ip_address_display',
        'entry_hash',
        'prev_hash',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * DB datetime columns store no fractional seconds, so both the
     * write-time hash and the verify-time hash must format `created_at`
     * at this same precision — otherwise every entry fails verification
     * even when nothing was tampered with.
     */
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a hash-chained activity log entry.
     * See 03-Auth-Security.md §20.7.
     *
     * `created_at` is generated once here, persisted verbatim (fillable
     * isn't used for it — Eloquent's own auto-timestamping would silently
     * overwrite it with a *different* instant, breaking the chain), and
     * hashed at the same precision it's stored at.
     */
    public static function record(
        $userId,
        string $action,
        ?string $modelType = null,
        $modelId = null,
        ?array $payload = null,
        ?string $ip = null
    ): self {
        $prev = self::latest('id')->first();
        $prevHash = $prev?->entry_hash ?? str_repeat('0', 64);

        $ip ??= request()->ip();
        $now = now();

        $fields = [
            'user_id'    => $userId,
            'action'     => $action,
            'model_type' => $modelType,
            'model_id'   => $modelId,
            'payload'    => $payload,
            'ip_address' => $ip,
        ];

        $entryHash = hash('sha256', $prevHash . self::hashableSnapshot($fields, $now));

        $log = new self($fields);
        $log->forceFill([
            'ip_address_display' => self::maskIp($ip),
            'prev_hash'          => $prevHash,
            'entry_hash'         => $entryHash,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
        $log->save();

        return $log;
    }

    /**
     * Verify the tamper-evident hash chain across all entries, in order.
     * Run daily via `php artisan logs:verify` (see 03-Auth-Security.md, 11-Testing-Maintenance.md).
     */
    public static function verifyChainIntegrity(): bool
    {
        $prevHash = str_repeat('0', 64);

        foreach (self::orderBy('id')->cursor() as $log) {
            $fields = [
                'user_id'    => $log->user_id,
                'action'     => $log->action,
                'model_type' => $log->model_type,
                'model_id'   => $log->model_id,
                'payload'    => $log->payload,
                'ip_address' => $log->ip_address,
            ];

            $expected = hash('sha256', $prevHash . self::hashableSnapshot($fields, $log->created_at));

            if (!hash_equals($expected, (string) $log->entry_hash)) {
                return false;
            }

            $prevHash = $log->entry_hash;
        }

        return true;
    }

    /**
     * Canonical JSON representation of a log entry's hashable fields.
     * Used identically at write time and verify time so the two never
     * drift apart because of cast/precision differences.
     */
    private static function hashableSnapshot(array $fields, \DateTimeInterface $createdAt): string
    {
        $fields['payload'] = self::canonicalize($fields['payload'] ?? null);

        return json_encode($fields + [
            'created_at' => $createdAt->format(self::TIMESTAMP_FORMAT),
        ]);
    }

    /**
     * Recursively sort array keys so the hash doesn't depend on
     * PHP's or the DB driver's member-ordering behavior for JSON
     * objects — without this, a value that round-trips through the
     * `payload` JSON column in a different key order would look like
     * tampering even though nothing changed.
     */
    private static function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $out = [];

        foreach ($value as $key => $item) {
            $out[$key] = self::canonicalize($item);
        }

        if (!$isList) {
            ksort($out);
        }

        return $out;
    }

    /**
     * Privacy-friendly IP for display in the admin UI (last octet / last
     * IPv6 group masked). The full `ip_address` is retained for the hash
     * chain and any actual security investigation.
     */
    private static function maskIp(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        if (str_contains($ip, '.')) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = '***';
                return implode('.', $parts);
            }
        }

        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            $parts[count($parts) - 1] = '****';
            return implode(':', $parts);
        }

        return $ip;
    }
}
