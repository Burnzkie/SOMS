{{-- resources/views/officer/audit/findings/show.blade.php --}}
<x-layout title="Audit Finding #{{ $finding->id }}">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Finding #{{ $finding->id }}</h1>
    <p class="meta">
      @include('partials.audit-badge', ['kind' => 'severity', 'value' => $finding->severity])
      @include('partials.audit-badge', ['kind' => 'finding', 'value' => $finding->status])
      &middot; reported {{ $finding->reported_at->format('M j, Y g:i A') }} by {{ $finding->reporter->name }}
    </p>
  </div>
  <div><a href="{{ route('officer.audit.findings.index') }}" class="btn btn-ghost">All findings</a></div>
</div>

@if($errors->any())
  <div class="banner danger">{{ $errors->first() }}</div>
@endif

<div class="panel">
  @if($canManage && $finding->isEditable())
  <form method="POST" action="{{ route('officer.audit.findings.update', $finding) }}">
    @csrf @method('PATCH')
    <div class="field" style="margin-bottom:14px;"><label>Title</label>
      <input type="text" name="title" value="{{ old('title', $finding->title) }}" required maxlength="160" class="field-input" style="width:100%;"></div>
    <div class="field" style="margin-bottom:14px;"><label>Issue discovered</label>
      <textarea name="issue" required maxlength="2000" class="field-input" style="width:100%; min-height:110px;">{{ old('issue', $finding->issue) }}</textarea></div>
    <div class="field" style="margin-bottom:14px;"><label>Evidence / notes</label>
      <textarea name="evidence" maxlength="2000" class="field-input" style="width:100%; min-height:90px;">{{ old('evidence', $finding->evidence) }}</textarea></div>
    <div class="field" style="margin-bottom:20px;"><label>Severity</label>
      <select name="severity" class="field-input">
        @foreach(\App\Models\AuditFinding::SEVERITIES as $s)
          <option value="{{ $s }}" @selected(old('severity', $finding->severity) === $s)>{{ ucfirst($s) }}</option>
        @endforeach
      </select></div>
    <button class="btn btn-primary" style="width:auto; padding:0 24px;">Save changes</button>
  </form>
  @else
    <h3>{{ $finding->title }}</h3>
    <p style="white-space:pre-line;">{{ $finding->issue }}</p>
    @if($finding->evidence)<h4>Evidence / notes</h4><p style="white-space:pre-line;">{{ $finding->evidence }}</p>@endif
  @endif
  <p class="meta">Record: {{ $finding->reference_type }}@if($finding->reference_id) #{{ $finding->reference_id }}@endif</p>
</div>

@if($finding->resolution_note)
<div class="panel">
  <div class="panel-head"><h3>Outcome</h3></div>
  <p style="white-space:pre-line;">{{ $finding->resolution_note }}</p>
  <p class="meta">{{ ucfirst($finding->status) }} by {{ $finding->resolver?->name ?? '—' }} on {{ $finding->resolved_at?->format('M j, Y g:i A') }}</p>
</div>
@endif

@if($canManage && in_array($finding->status, ['open', 'acknowledged'], true))
<div class="panel">
  <div class="panel-head"><h3>Withdraw this finding</h3></div>
  <form method="POST" action="{{ route('officer.audit.findings.withdraw', $finding) }}">
    @csrf
    <input type="text" name="resolution_note" required minlength="5" maxlength="1000" class="field-input" style="width:100%; margin-bottom:10px;" placeholder="Why is this being withdrawn? (kept in the record)">
    <button class="btn btn-ghost" style="width:auto; padding:0 20px;">Withdraw</button>
  </form>
</div>
@endif

@if($canManage && in_array($finding->status, ['resolved', 'dismissed'], true))
<div class="panel">
  <div class="panel-head"><h3>Problem still there?</h3></div>
  <form method="POST" action="{{ route('officer.audit.findings.reopen', $finding) }}">
    @csrf
    <input type="text" name="reason" required minlength="5" maxlength="1000" class="field-input" style="width:100%; margin-bottom:10px;" placeholder="Why is this being reopened?">
    <button class="btn btn-ghost" style="width:auto; padding:0 20px;">Reopen</button>
  </form>
</div>
@endif
@endslot

</x-layout>
