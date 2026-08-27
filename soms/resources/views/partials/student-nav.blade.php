<div class="nav-group">
    <div class="nav-label">Overview</div>
    <a href="{{ route('student.dashboard') }}" class="nav-link @if(request()->routeIs('student.dashboard')) active @endif">
        <span class="ic"></span>
        Dashboard
    </a>
    <a href="{{ route('student.qr.show') }}" class="nav-link @if(request()->routeIs('student.qr.*')) active @endif">
        <span class="ic"></span>
        My QR
    </a>
</div>

<div class="nav-group">
    <div class="nav-label">Organization</div>
    <a href="{{ route('student.events.index') }}" class="nav-link @if(request()->routeIs('student.events.*')) active @endif">
        <span class="ic"></span>
        Events
    </a>
    <a href="{{ route('student.fines.index') }}" class="nav-link @if(request()->routeIs('student.fines.*')) active @endif">
        <span class="ic"></span>
        Fines
    </a>
    <a href="{{ route('student.announcements.index') }}" class="nav-link @if(request()->routeIs('student.announcements.*')) active @endif">
        <span class="ic"></span>
        Announcements
    </a>
</div>
