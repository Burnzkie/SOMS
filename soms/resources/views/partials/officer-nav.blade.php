<div class="nav-group">
    <div class="nav-label">Overview</div>
    <a href="{{ route('officer.dashboard') }}" class="nav-link @if(request()->routeIs('officer.dashboard')) active @endif">
        <span class="ic"></span>
        Dashboard
    </a>
</div>

<div class="nav-group">
    <div class="nav-label">Operations</div>

    @php($perms = $permissions ?? ['manage_events' => false, 'manage_attendance' => false, 'draft_announcements' => false, 'manage_announcements' => false, 'view_calendar' => false, 'manage_calendar' => false])
    @php($treasurer = $isTreasurer ?? false)

    @if(($perms['manage_events'] ?? false) || ($perms['manage_attendance'] ?? false))
        <a href="{{ route('officer.events.index') }}" class="nav-link @if(request()->routeIs('officer.events.*') || request()->routeIs('officer.attendance.*')) active @endif">
            <span class="ic"></span>
            Events &amp; Attendance
        </a>
    @endif

    @if($treasurer)
        <a href="{{ route('officer.fines.index') }}" class="nav-link @if(request()->routeIs('officer.fines.*')) active @endif">
            <span class="ic"></span>
            Fines
        </a>
    @endif

    @if(($perms['draft_announcements'] ?? false) || ($perms['manage_announcements'] ?? false))
        <a href="{{ route('officer.announcements.index') }}" class="nav-link @if(request()->routeIs('officer.announcements.*')) active @endif">
            <span class="ic"></span>
            Announcements
        </a>
    @endif

    @if(($perms['view_calendar'] ?? false) || ($perms['manage_calendar'] ?? false))
        <a href="{{ route('officer.calendar.index') }}" class="nav-link @if(request()->routeIs('officer.calendar.*')) active @endif">
            <span class="ic"></span>
            Calendar
        </a>
    @endif
</div>

<div class="nav-group">
    <a href="{{ route('settings.profile.edit') }}" class="nav-link @if(request()->routeIs('settings.*')) active @endif">
        <span class="ic"></span>
        Settings
    </a>
</div>
