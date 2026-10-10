<?php
// app/Models/AnalyticsDailyHit.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per (date, route_name, platform) — see the migration docblock
 * for why this table intentionally carries no personal data at all.
 */
class AnalyticsDailyHit extends Model
{
    protected $fillable = ['date', 'route_name', 'platform', 'hits'];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];
}
