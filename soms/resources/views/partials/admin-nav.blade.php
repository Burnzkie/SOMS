<div class="nav-group">
        <div class="nav-label"> Overview </div>
        <a href="{{ route('admin.dashboard') }}" class="nav-link @if(request()->routeIs('admin.dashboard')) active @endif"> 
            <span class="ic"> </span> 
            Dashboard
        </a>
</div>

    <div class="nav-group">
       <div class="nav-label">Management</div>
       <a href="{{ route('admin.users.index') }}" class="nav-link @if(request()->routeIs('admin.users.*')) active @endif">
        <span class="ic"></span>
        Users @if(($pendingApprovals ?? 0) > 0)
        <span class="badge-count">{{$pendingApprovals}}</span>
        @endif
       </a>
            <a href="{{ route('admin.officers.index') }}" class="nav-link @if(request()->routeIs('admin.officers.*')) active @endIf">
                <span class="ic"></span>
                Officer Appointment
            </a>
                    <a href="{{ route('admin.reports.index') }}" class="nav-link @if(request()->routeIs('admin.reports.*')) active @endIf">
                        <span class="ic"></span>
                        Reports
                    </a>
                            <a href="{{ route('admin.activity-logs.index') }}" class="nav-link @if(request()->routeIs('admin.activity-logs.*')) active @endIf">
                                <span class="ic"></span>
                                Activity Logs
                            </a>
    </div>