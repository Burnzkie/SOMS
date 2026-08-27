<x-layout :title="'Activity Log — ' . $targetUser->name">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')

<div class="topbar">
    <div>
        <h1>{{ $targetUser->name }}</h1>
        <p class="meta">{{ $targetUser->student_id }} · Activity log</p>
    </div>
    <div class="topbar-actions">
        <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">← Back to Users</a>
    </div>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('M j, Y g:i A') }}</td>
                    <td>{{ $log->action }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="empty-note">No activity logged for this user yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">{{ $logs->links() }}</div>
</div>
@endslot

</x-layout>
