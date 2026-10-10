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

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Officer</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($panel as $row)
                <tr>
                    <td>{{$row['position']}}</td>
                    <td>
                        @if($row['vacant'])
                            <span class="text-faint">—</span>
                        @else
                            <div class="user-cell">
                                @if($row['officer']->avatar_url)
                                    <img src="{{ $row['officer']->avatar_url }}"
                                         alt="{{ $row['officer']->name }}" class="avatar-sm" style="object-fit:cover;">
                                @else
                                    <span class="avatar-sm"></span>
                                @endif
                                <div>
                                    <div>{{$row['officer']->name}}</div>
                                    <div class="sub">{{$row['officer']->student_id}}</div>
                                </div>
                            </div>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{$row['vacant'] ? 'pending' : 'approved'}}">
                            {{$row['vacant'] ? 'Vacant' : 'Filled'}}
                        </span>
                    </td>
                    <td>
                        <div class="queue-actions">
                            @if($row['vacant'])
                            <form method="POST" action="{{ route('admin.officers.appoint') }}" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
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
                            <form method="POST" action="{{ route('admin.officers.revoke', $row['position_id']) }}" onsubmit="return confirm('Revoke {{ $row['officer']->name }} from {{ $row['position'] }}?');">
                                @csrf
                                <button type="submit" class="mini-btn reject">Revoke</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endslot

</x-layout>
