<x-layout title="Reports">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')

<div class="topbar">
    <div>
        <h1>Reports</h1>
        <p class="meta">Itemized fine collection - Treasurer &amp; Admin only</p>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon green">₱</div>
        <div class="value">₱{{number_format($totalCollected, 2)}}</div>
        <div class="label">Total collected (paid)</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon amber">₱</div>
        <div class="value">₱{{number_format($totalWaived, 2)}}</div>
        <div class="label">Total waived (audit only, not revenue)</div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3>Students with fine records</h3>
        <span class="link-sm disabled">View only</span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Department</th>
                    <th>Fines</th>
                    <th>Paid</th>
                    <th>Waived</th>
                </tr>
            </thead>
            <tbody>
                @forelse($studentsWithFines as $row)
                <tr>
                    <td>
                        <div class="user-cell">
                            <span class="avatar-sm"></span>
                            <span>{{$row->user->name}}</span>
                        </div>
                    </td>
                    <td>{{$row->user->department}}</td>
                    <td>{{$row->fine_count}}</td>
                    <td>
                        @if($row->total_paid > 0)
                        <span class="badge paid">₱{{number_format($row->total_paid, 2)}}</span>
                        @else
                        <span class="text-faint">—</span>
                        @endif
                    </td>
                    <td>
                        @if($row->total_waived > 0)
                        <span class="badge waived">₱{{number_format($row->total_waived, 2)}}</span>
                        @else
                        <span class="text-faint">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="empty-note">No fine records yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-head"><h3>Paid fines</h3></div>
    @forelse($paid as $fine)
    <div class="queue-item">
        <div class="who">
            <b>{{$fine->user->name}}</b>
            <span>{{$fine->violation_type}} &middot; ₱{{number_format($fine->amount, 2)}}</span>
        </div>
        <div class="queue-actions">
            <span class="badge paid">Paid</span>
        </div>
    </div>
    @empty
    <p class="empty-note">No paid fines yet.</p>
    @endforelse
</div>

<div class="panel">
    <div class="panel-head"><h3>Waived Fines</h3></div>
    @forelse($waived as $fine)
    <div class="queue-item">
        <div class="who">
            <b>{{$fine->user->name}}</b>
            <span>{{$fine->violation_type}} &middot; ₱{{number_format($fine->amount, 2)}}</span>
        </div>
        <div class="queue-actions">
            <span class="badge waived">Waived</span>
        </div>
    </div>
    @empty
    <p class="empty-note">No waived fines yet.</p>
    @endforelse
</div>
@endslot

</x-layout>
