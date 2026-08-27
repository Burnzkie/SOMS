<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendance extends Model
{
    protected $table = 'event_attendance';

    protected $fillable = [
        'event_id',
        'event_day_id',
        'event_session_id',
        'user_id',
        'scan_type',
        'scanned_at',
        'marked_by',
        'status',
        'is_manual_override',
        'override_reason',
        'device_scanned_at',
        'synced_from_offline_queue',
    ];

    protected $casts = [
        'scanned_at'                 => 'datetime',
        'device_scanned_at'          => 'datetime',
        'is_manual_override'         => 'boolean',
        'synced_from_offline_queue'  => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class);
    }

    public function eventSession(): BelongsTo
    {
        return $this->belongsTo(EventSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
