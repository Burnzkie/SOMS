<?php
// app/Models/AuditFinding.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audit finding: a discrepancy or concern the Auditor reported, and how
 * it was dealt with. Reporting a finding never edits the record it refers to.
 */
class AuditFinding extends Model
{
    public const STATUSES = ['open', 'acknowledged', 'resolved', 'dismissed'];
    public const SEVERITIES = ['low', 'medium', 'high'];
    public const REFERENCE_TYPES = ['fine', 'attendance', 'session', 'general'];

    protected $fillable = [
        'organization_id',
        'reference_type',
        'reference_id',
        'check_type',
        'title',
        'issue',
        'evidence',
        'severity',
        'status',
        'reported_by',
        'reported_at',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Findings still needing action (not yet resolved or dismissed). */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['open', 'acknowledged']);
    }

    /** The Auditor may edit a finding's content only while it is still open. */
    public function isEditable(): bool
    {
        return $this->status === 'open';
    }
}
