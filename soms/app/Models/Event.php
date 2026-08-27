<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'organization_id',
        'created_by',
        'title',
        'description',
        'venue',
        'type',
        'date_start',
        'date_end',
        'has_parade',
        'is_published',
    ];

    protected $casts = [
        'date_start'   => 'date',
        'date_end'     => 'date',
        'has_parade'   => 'boolean',
        'is_published' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function eventDays(): HasMany
    {
        return $this->hasMany(EventDay::class);
    }

    public function fineRules(): HasMany
    {
        return $this->hasMany(EventFineRule::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    public function fines(): HasMany
    {
        return $this->hasMany(Fine::class);
    }
}
