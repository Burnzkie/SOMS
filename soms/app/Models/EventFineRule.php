<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFineRule extends Model
{
    /**
     * This table has no created_at/updated_at columns.
     * See 02-Database-Schema.md §8.
     */
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'violation_type',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}