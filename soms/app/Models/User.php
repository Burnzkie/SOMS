<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Mass-assignable attributes.
     *
     * Sensitive fields are deliberately excluded — see 01-Overview-Architecture.md
     * Decision 2.7: role, is_approved, approved_by, must_change_password,
     * qr_generated_at, deleted_at are mutated only via forceFill() in
     * explicit controller logic. (qr_token/qr_version/qr_revoked were
     * removed — QR is now a live rotating token, never stored. See
     * App\Services\QrTokenService and 05-Attendance-Fines.md Part B.)
     */
    protected $fillable = [
        'name',
        'student_id',
        'email',
        'department',
        'program',
        'level',
        'password',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'fcm_token',
    ];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'password'              => 'hashed',
        'is_approved'           => 'boolean',
        'must_change_password'  => 'boolean',
        'qr_generated_at'       => 'datetime',
    ];

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function officerPositions(): HasMany
    {
        return $this->hasMany(OfficerPosition::class);
    }

    /**
     * The user's currently active officer position, if any.
     * Used by OfficerPermission::tier() and ::isTreasurer()
     * (see 04-Officer-Permissions-Members.md).
     */
    public function activeOfficerPosition(): HasOne
    {
        return $this->hasOne(OfficerPosition::class)->where('is_active', true);
    }

    public function fines(): HasMany
    {
        return $this->hasMany(Fine::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // -----------------------------------------------------------------
    // Scopes — see 02-Database-Schema.md, Eloquent Model Rules
    // -----------------------------------------------------------------

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeStudents($query)
    {
        return $query->where('role', 'student');
    }

    public function scopeOfficers($query)
    {
        return $query->where('role', 'officer');
    }
}