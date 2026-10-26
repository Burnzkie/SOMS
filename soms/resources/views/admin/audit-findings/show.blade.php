{{-- resources/views/admin/audit-findings/show.blade.php --}}
<x-layout title="Audit Finding #{{ $finding->id }}">

@slot('nav')
@include('partials.admin-nav')
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
  <div><a href="{{ route('admin.audit-findings.index') }}" class="btn btn-ghost">All findings</a></div>
</div>

@if($errors->any())
  <div class="banner danger">{{ $errors->first() }}</div>
@endif

<div class="panel">
  <h3>{{ $finding->title }}</h3>
  <p style="white-space:pre-line;">{{ $finding->issue }}</p>
  @if($finding->evidence)<h4>Evidence / notes</h4><p style="white-space:pre-line;">{{ $finding->evidence }}</p>@endif
  <p class="meta">Record: {{ $finding->reference_type }}@if($finding->reference_id) #{{ $finding->reference_id }}@endif</p>
</div>

@if($finding->resolution_note)
<div class="panel">
  <div class="panel-head"><h3>Outcome</h3></div>
  <p style="white-space:pre-line;">{{ $finding->resolution_note }}</p>
  <p class="meta">{{ ucfirst($finding->status) }} by {{ $finding->resolver?->name ?? '—' }} on {{ $finding->resolved_at?->format('M j, Y g:i A') }}</p>
</div>
@endif

@if(in_array($finding->status, ['open', 'acknowledged'], true))
<div class="panel">
  <div class="panel-head"><h3>Respond</h3></div>

  @if($finding->status === 'open')
  <form method="POST" action="{{ route('admin.audit-findings.respond', $finding) }}" style="margin-bottom:16px;">
    @csrf
    <input type="hidden" name="action" value="acknowledge">
    <button class="btn btn-ghost" style="width:auto; padding:0 20px;">Acknowledge (received, being handled)</button>
  </form>
  @endif

  <form method="POST" action="{{ route('admin.audit-findings.respond', $finding) }}">
    @csrf
    <input type="hidden" name="action" value="resolve">
    <textarea name="resolution_note" required minlength="5" maxlength="1000" class="field-input" style="width:100%; min-height:90px; margin-bottom:10px;" placeholder="What was done to resolve this?">{{ old('resolution_note') }}</textarea>
    <button class="btn btn-primary" style="width:auto; padding:0 24px;">Mark resolved</button>
  </form>
</div>
@endif
@endslot

</x-layout>
