<?php
// app/Models/EventSession.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSession extends Model
{
    protected $fillable = [
        'event_day_id',
        'session_type',
        'is_parade',
        'name',
        'sort_order',
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
        'is_parade'     => 'boolean',
        'fines_issued'  => 'boolean',
    ];

    /** Display name: a custom session's own name, otherwise "Morning" / "Afternoon". */
    public function getLabelAttribute(): string
    {
        return $this->name ?: ucfirst($this->session_type);
    }

    /**
     * Which scan a QR read at this moment counts as: 'time_in' inside the
     * time-in window, 'time_out' inside the (optional) time-out window, or
     * null when neither is open. Time-in wins if the two windows overlap.
     */
    public function activeScanType(\Carbon\CarbonInterface $at): ?string
    {
        if ($at->betweenIncluded($this->timein_start, $this->timein_end)) {
            return 'time_in';
        }

        if ($this->timeout_start && $this->timeout_end && $at->betweenIncluded($this->timeout_start, $this->timeout_end)) {
            return 'time_out';
        }

        return null;
    }

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