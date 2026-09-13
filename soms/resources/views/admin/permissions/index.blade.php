<x-layout title="Permissions">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')
<div class="topbar">
    <div>
        <h1>Permissions</h1>
        <p class="meta">Select an officer to grant or revoke what they can access.</p>
    </div>
</div>

@if(session('status'))
<div class="banner success">{{session('status')}}</div>
@endif

<div class="panel">
    <div class="panel-head"><h3>Officers</h3></div>

    @forelse($officers as $position)
    <a href="{{ route('admin.permissions.edit', $position) }}" class="queue-item" style="text-decoration:none; color:inherit;">
        <div class="who">
            <b>{{ $position->user->name }}</b>
            <span>{{ $position->position_title }} &middot; {{ $position->user->student_id }}</span>
        </div>
        <div class="queue-actions">
            <span class="badge {{ count($position->permissions ?? []) ? 'approved' : 'pending' }}">
                {{ count($position->permissions ?? []) }} permission{{ count($position->permissions ?? []) === 1 ? '' : 's' }} granted
            </span>
        </div>
    </a>
    @empty
    <p class="meta">No active officers yet — appoint one first from Officer Appointment.</p>
    @endforelse
</div>
@endslot

</x-layout>
