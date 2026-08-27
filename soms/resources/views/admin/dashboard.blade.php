<x-layout title="Admin Dashboard">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Welcome back, {{auth()->user()->name}}</h1>
    <p class="meta">{{now()->format('l, F j, Y')}}</p>
  </div>
  @forelse($recentPendingStudents as $student)
      <div class="queue-item">
        <div class="avatar-sm"></div>
        <div class="who">
          <b>{{$student->name}}</b>
          <span>{{$student->student_id}} . {{$student->department}}</span>
        </div>
      </div>
      @empty
      <p class="empty-note">No pending approvals right now.</p>
      @endforelse
</div>

@unless ($treasurerActive)
<div class="banner warn">⚠ No active Treasurer - Admin is the fallback for clearing/waiving fines until one is appointed.</div>
@endunless

@unless($logChainOk)
  <div class="banner danger">⚠ Activity log chain integrity check failed - review immediately.</div>
  @endunless

  @if($queueStale)
  <div class="banner warn">⚠ Fine-issuance queue worker hasn't reported in over 2 hours - check the Render worker process. See <a href="{{ route('admin.system-health') }}">system health</a>.</div>
  @endif

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon violet">👥</div>
      <div class="value">{{$totalStudents}}</div>
      <div class="label">Approved students</div>
    </div>
  
        <div class="stat-card">
          <div class="stat-icon green">🧑‍💼</div>
          <div class="value">{{$activeOfficers}}</div>
          <div class="label">Active officers</div>
        </div>
              <div class="stat-card">
                <div class="stat-icon amber">⏳</div>
                <div class="value">{{$pendingApprovals}}</div>
                <div class="label">Pending approvals</div>
              </div>
                    <div class="stat-card">
                      <div class="stat-icon rose">₱</div>
                      <div class="value">₱{{number_format($totalUnpaidFines, 2)}}</div>
                      <div class="label">Total unpaid fines</div>
                    </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <h3>Pending student approvals</h3>
        <span class="note">{{$pendingApprovals}} total</span>
      </div>

      @forelse($recentPendingStudents as $student)
      <div class="queue-item">
        <div class="avatar-sm"></div>
        <div class="who">
          <b>{{$student->name}}</b>
          <span>{{$student->student_id}} . {{$student->department}}</span>
        </div>
      </div>
      @empty
      <p class="empty-note">No pending approvals right now.</p>
      @endforelse
    </div>
    @endslot

</x-layout>
