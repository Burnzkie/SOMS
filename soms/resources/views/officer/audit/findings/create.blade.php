{{-- resources/views/officer/audit/findings/create.blade.php --}}
<x-layout title="New Audit Finding">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar"><div><h1>New audit finding</h1><p class="meta">Describe what you found. This records the issue; it does not change the record itself.</p></div></div>

<div class="panel">
  <form method="POST" action="{{ route('officer.audit.findings.store') }}">
    @csrf
    @if($errors->any())
      <div class="banner danger">{{ $errors->first() }}</div>
    @endif

    <div class="field" style="margin-bottom:14px;">
      <label>Record involved</label>
      <div style="display:flex; gap:10px;">
        <select name="reference_type" class="field-input">
          @foreach(\App\Models\AuditFinding::REFERENCE_TYPES as $type)
            <option value="{{ $type }}" @selected(old('reference_type', $referenceType) === $type)>{{ ucfirst($type) }}</option>
          @endforeach
        </select>
        <input type="number" name="reference_id" min="1" value="{{ old('reference_id', $referenceId) }}" class="field-input" placeholder="Record ID (optional)">
      </div>
    </div>
    <div class="field" style="margin-bottom:14px;">
      <label>Title</label>
      <input type="text" name="title" value="{{ old('title') }}" required maxlength="160" class="field-input" style="width:100%;">
    </div>
    <div class="field" style="margin-bottom:14px;">
      <label>Issue discovered</label>
      <textarea name="issue" required maxlength="2000" class="field-input" style="width:100%; min-height:110px;">{{ old('issue') }}</textarea>
    </div>
    <div class="field" style="margin-bottom:14px;">
      <label>Evidence / notes</label>
      <textarea name="evidence" maxlength="2000" class="field-input" style="width:100%; min-height:90px;">{{ old('evidence') }}</textarea>
    </div>
    <div class="field" style="margin-bottom:20px;">
      <label>Severity</label>
      <select name="severity" class="field-input">
        @foreach(\App\Models\AuditFinding::SEVERITIES as $s)
          <option value="{{ $s }}" @selected(old('severity', 'medium') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
      </select>
    </div>
    <button class="btn btn-primary" style="width:auto; padding:0 24px;">Record finding</button>
  </form>
</div>
@endslot

</x-layout>
