<?php
// app/Services/AuditFindingService.php

namespace App\Services;

use App\Exceptions\InvalidFindingTransition;
use App\Models\ActivityLog;
use App\Models\AuditFinding;

/**
 * The Admin's response to an audit finding: acknowledge it, or resolve it
 * with a note. One place for the rules, shared by the web and mobile (API)
 * controllers so they cannot disagree. Every change is written to the
 * activity log.
 */
class AuditFindingService
{
    /**
     * @param  string      $action 'acknowledge' | 'resolve'
     * @throws InvalidFindingTransition when the finding's current status doesn't allow it
     */
    public function respond(AuditFinding $finding, string $action, ?string $note, int $adminId): void
    {
        if ($action === 'acknowledge') {
            if ($finding->status !== 'open') {
                throw new InvalidFindingTransition('Only an open finding can be acknowledged.');
            }

            $finding->update(['status' => 'acknowledged']);
            ActivityLog::record($adminId, 'audit_finding_acknowledged', AuditFinding::class, $finding->id);

            return;
        }

        if (!in_array($finding->status, ['open', 'acknowledged'], true)) {
            throw new InvalidFindingTransition('This finding is already closed.');
        }

        $finding->update([
            'status'          => 'resolved',
            'resolution_note' => $note,
            'resolved_by'     => $adminId,
            'resolved_at'     => now(),
        ]);
        ActivityLog::record($adminId, 'audit_finding_resolved', AuditFinding::class, $finding->id, ['note' => $note]);
    }
}
