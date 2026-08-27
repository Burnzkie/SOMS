<x-layout title="Officer Appointment">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')
<div class="topbar">
    <div>
        <h1>Officer Appointment</h1>
        <p class="meta">Academic year {{$academicYear}}</p>
    </div>
</div>

@if(session('status'))
<div class="banner success">{{session('status')}}</div>
@endif

<div class="panel">
    <div class="panel-head"><h3>Positions</h3></div>

    @foreach($panel as $row)
    <div class="queue-item">
        <div class="who">
            <b>{{$row['position']}}</b>
            <span>
                @if($row['vacant'])
                <span class="badge pending">Vacant</span>
                @else
                <span class="badge approved">Filled</span> &middot; {{ $row['officer']->name }} ({{ $row['officer']->student_id }})
                @endif
            </span>
        </div>
        @if($row['vacant'])
        <form method="POST" action="{{ route('admin.officers.appoint') }}" class="queue-actions">
            @csrf
            <input type="hidden" name="position_title" value="{{ $row['position'] }}">
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <select name="user_id" required class="field-input" style="font-size:11px;">
                <option value="">Select student....</option>
                @foreach($approvedStudents as $student)
                <option value="{{ $student->id }}">{{$student->name}} ({{ $student->student_id }})</option>
                @endforeach
            </select>
            <button type="submit" class="mini-btn approve">Appoint</button>
        </form>
        @else
        <form method="POST" action="{{ route('admin.officers.revoke', $row['position_id']) }}" class="queue-actions" onsubmit="return confirm('Revoke {{ $row['officer']->name }} from {{ $row['position'] }}?');">
            @csrf
            <button type="submit" class="mini-btn reject">Revoke</button>
        </form>
        @endif
    </div>
    @endforeach
</div>
@endslot

</x-layout>
