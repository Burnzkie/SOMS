<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSession extends Model
{
    protected $fillable = [
        'event_day_id',
        'session_type',
        'timein_start',
        'timein_end',
        'timeout_start',
        'timeout_end',
        'fines_issued',
    ];

    protected $casts = [
        'timein_start'  => 'datetime',
        'timein_end'    => 'datetime',
        'timeout_start' => 'datetime',
        'timeout_end'   => 'datetime',
        'fines_issued'  => 'boolean',
    ];

    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    public function delegates(): HasMany
    {
        return $this->hasMany(AttendanceDelegate::class);
    }

    public function fines(): HasMany
    {
        return $this->hasMany(Fine::class);
    }
}
