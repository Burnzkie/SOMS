<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppVersion extends Model
{
    protected $fillable = [
        'platform',
        'version_code',
        'version_name',
        'apk_url',
        'changelog',
        'force_update',
        'min_supported_version_code',
        'is_active',
        'published_by',
    ];

    protected $casts = [
        'force_update' => 'boolean',
        'is_active'    => 'boolean',
        'version_code' => 'integer',
        'min_supported_version_code' => 'integer',
    ];

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * The row the app should compare itself against — highest version_code
     * among active rows for the platform. Not just "most recently created":
     * a hotfix published for an older line, or a mistaken insert order,
     * should never make the app step backward.
     */
    public static function latestFor(string $platform = 'android'): ?self
    {
        return static::where('platform', $platform)
            ->where('is_active', true)
            ->orderByDesc('version_code')
            ->first();
    }
}
