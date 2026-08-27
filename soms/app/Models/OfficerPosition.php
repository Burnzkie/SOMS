<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficerPosition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'organization_id',
        'position_title',
        'academic_year',
        'is_active',
        'appointed_at',
        'appointed_by',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'appointed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function appointedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appointed_by');
    }

    /**
     * Only currently-active rows for a given position/academic year.
     * Used by the Officer Appointment panel to check for conflicts
     * before appointing — see 02-Database-Schema.md.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
