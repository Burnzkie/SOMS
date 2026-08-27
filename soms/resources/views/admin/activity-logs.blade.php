<x-layout title="Activity Logs">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')

<div class="topbar">
    <div>
        <h1>Activity Logs</h1>
        <p class="meta">Chain integrity: {{$logChainOk ? 'OK' : 'BROKEN'}}</p>
    </div>
</div>

@unless($logChainOk)
<div class="banner danger">⚠ Activity log chain integrity check failed - review immediately.</div>
@endunless

<div class="panel">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>{{$log->created_at->format('M j, Y g:i A')}}</td>
                    <td>
                        <div class="user-cell">
                            <span class="avatar-sm"></span>
                            <span>{{$log->user->name ?? 'System'}}</span>
                        </div>
                    </td>
                    <td>{{$log->action}}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="empty-note">No activity logs yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">{{$logs->links()}}</div>
</div>
@endslot

</x-layout>
