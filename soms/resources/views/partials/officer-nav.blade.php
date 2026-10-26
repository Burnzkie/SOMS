<div class="nav-group">
    <div class="nav-label">Overview</div>
    <a href="{{ route('officer.dashboard') }}" class="nav-link @if(request()->routeIs('officer.dashboard')) active @endif">
        <span class="ic orange">🏠</span>
        Dashboard
    </a>
</div>

<div class="nav-group">
    <div class="nav-label">Operations</div>

    @php($perms = $permissions ?? ['manage_events' => false, 'manage_attendance' => false, 'draft_announcements' => false, 'manage_announcements' => false, 'view_calendar' => false, 'manage_calendar' => false])
    @php($treasurer = $isTreasurer ?? false)

    @if(($perms['manage_events'] ?? false) || ($perms['manage_attendance'] ?? false))
        <a href="{{ route('officer.events.index') }}" class="nav-link @if(request()->routeIs('officer.events.*') || request()->routeIs('officer.attendance.*')) active @endif">
            <span class="ic orange">📅</span>
            Events &amp; Attendance
        </a>
    @endif

    @if($treasurer)
        <a href="{{ route('officer.fines.index') }}" class="nav-link @if(request()->routeIs('officer.fines.*')) active @endif">
            <span class="ic green">💲</span>
            Fines
        </a>
    @endif

    @if(($perms['draft_announcements'] ?? false) || ($perms['manage_announcements'] ?? false))
        <a href="{{ route('officer.announcements.index') }}" class="nav-link @if(request()->routeIs('officer.announcements.*')) active @endif">
            <span class="ic amber">📣</span>
            Announcements
        </a>
    @endif

    @if(($perms['view_calendar'] ?? false) || ($perms['manage_calendar'] ?? false))
        <a href="{{ route('officer.calendar.index') }}" class="nav-link @if(request()->routeIs('officer.calendar.*')) active @endif">
            <span class="ic purple">🗓</span>
            Calendar
        </a>
    @endif
</div>

@php($canAudit = collect(['view_reports', 'view_fines', 'view_attendance', 'view_audit_logs', 'manage_audit_findings'])->contains(fn ($p) => $perms[$p] ?? false))
@if($canAudit)
<div class="nav-group">
    <div class="nav-label">Audit</div>

    @if($perms['view_reports'] ?? false)
        <a href="{{ route('officer.audit.dashboard') }}" class="nav-link @if(request()->routeIs('officer.audit.dashboard')) active @endif">
            <span class="ic green">🧮</span>
            Audit Overview
        </a>
        <a href="{{ route('officer.audit.discrepancies') }}" class="nav-link @if(request()->routeIs('officer.audit.discrepancies')) active @endif">
            <span class="ic rose">🔎</span>
            Discrepancies
        </a>
    @endif

    @if($perms['view_fines'] ?? false)
        <a href="{{ route('officer.audit.fines') }}" class="nav-link @if(request()->routeIs('officer.audit.fines')) active @endif">
            <span class="ic green">💲</span>
            Fines &amp; Payments
        </a>
    @endif

    @if($perms['view_attendance'] ?? false)
        <a href="{{ route('officer.audit.attendance') }}" class="nav-link @if(request()->routeIs('officer.audit.attendance')) active @endif">
            <span class="ic orange">📋</span>
            Attendance Records
        </a>
    @endif

    @if(($perms['manage_audit_findings'] ?? false) || ($perms['view_reports'] ?? false))
        <a href="{{ route('officer.audit.findings.index') }}" class="nav-link @if(request()->routeIs('officer.audit.findings.*')) active @endif">
            <span class="ic amber">📝</span>
            Audit Findings
        </a>
    @endif

    @if($perms['view_audit_logs'] ?? false)
        <a href="{{ route('officer.audit.activity-log') }}" class="nav-link @if(request()->routeIs('officer.audit.activity-log')) active @endif">
            <span class="ic">🧾</span>
            Activity Log
        </a>
    @endif
</div>
@endif

<div class="nav-group">
    <a href="{{ route('settings.profile.edit') }}" class="nav-link @if(request()->routeIs('settings.*')) active @endif">
        <span class="ic">⚙️</span>
        Settings
    </a>
</div>
