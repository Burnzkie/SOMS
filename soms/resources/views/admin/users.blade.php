<x-layout title="User Management">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')
<div class="topbar">
    <div>
        <h1>User Management</h1>
        <p class="meta">{{$users->total()}} total users</p>
    </div>
</div>

<div class="panel">
    <form method="GET" class="filter-form">
        <input type="text" name="search" value="{{request('search')}}" placeholder="Search by Student ID or name" class="field-input">
        <select name="role" class="field-input">
            <option value="">All roles</option>
            <option value="admin" @selected(request('role')==='admin')>Admin</option>
            <option value="officer" @selected(request('role')==='officer')>Officer</option>
            <option value="student" @selected(request('role')==='student')>Student</option>
        </select>
        <select name="status" class="field-input">
            <option value="">All statuses</option>
            <option value="pending" @selected(request('status')==='pending')>Pending</option>
            <option value="approved" @selected(request('status')==='approved')>Approved</option>
        </select>
        <button class="btn btn-primary" type="submit">Filter</button>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <div class="user-cell">
                            <span class="avatar-sm"></span>
                            <div>
                                <div>{{$user->name}}</div>
                                <div class="sub">{{$user->student_id}}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{$user->department}}</td>
                    <td>{{ucfirst($user->role)}}</td>
                    <td>
                        <span class="badge {{$user->is_approved ? 'approved' : 'pending'}}">
                            {{$user->is_approved ? 'Approved' : 'Pending'}}
                        </span>
                    </td>
                    <td>
                        <div class="queue-actions">
                            @if(!$user->is_approved)
                                <form method="POST" action="{{ route('admin.users.approve', $user) }}" onsubmit="return confirm('Approve {{ $user->name }}? This generates their QR code.');">
                                    @csrf
                                    <button type="submit" class="mini-btn approve">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.reject', $user) }}" onsubmit="return submitRejection(this)">
                                    @csrf
                                    <input type="hidden" name="reason" value="">
                                    <button type="submit" class="mini-btn reject">Reject</button>
                                </form>
                            @endif
                            <a href="{{ route('admin.users.activity-log', $user) }}" class="link-sm">View log</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="empty-note">No users match this filter</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">{{$users->links()}}</div>
</div>

<script>
function submitRejection(form) {
    var reason = prompt('Reason for rejection (min. 5 characters):');
    if (!reason || reason.trim().length < 5) {
        if (reason !== null) alert('Please enter a reason of at least 5 characters.');
        return false;
    }
    form.reason.value = reason.trim();
    return true;
}
</script>
@endslot

</x-layout>
