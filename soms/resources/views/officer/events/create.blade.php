<x-layout title="New Event">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>New event</h1>
    <p class="meta">Days and morning/afternoon sessions are generated automatically for the date range.</p>
  </div>
</div>

@if($errors->any())
<div class="banner" style="background:var(--rose-soft); color:var(--rose);">
  @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
</div>
@endif

<div class="panel">
  <form method="POST" action="{{ route('officer.events.store') }}">
    @csrf
    <div class="field" style="margin-bottom:14px;">
      <label>Title</label>
      <input type="text" name="title" value="{{ old('title') }}" required class="field-input" style="width:100%;">
    </div>
    <div class="field" style="margin-bottom:14px;">
      <label>Description</label>
      <textarea name="description" class="field-input" style="width:100%; min-height:80px;">{{ old('description') }}</textarea>
    </div>
    <div class="field" style="margin-bottom:14px;">
      <label>Venue</label>
      <input type="text" name="venue" value="{{ old('venue') }}" class="field-input" style="width:100%;">
    </div>
    <div style="display:flex; gap:14px; margin-bottom:14px;">
      <div class="field" style="flex:1;">
        <label>Type</label>
        <select name="type" class="field-input" style="width:100%;">
          <option value="foundation_day">Foundation Day</option>
          <option value="other" selected>Other</option>
        </select>
      </div>
      <div class="field" style="flex:1;">
        <label>Start date</label>
        <input type="date" name="date_start" value="{{ old('date_start') }}" required class="field-input" style="width:100%;">
      </div>
      <div class="field" style="flex:1;">
        <label>End date</label>
        <input type="date" name="date_end" value="{{ old('date_end') }}" required class="field-input" style="width:100%;">
      </div>
    </div>
    <div class="field" style="margin-bottom:20px;">
      <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
        <input type="checkbox" name="has_parade" value="1"> Include a Parade session (time-in only) on each day
      </label>
    </div>
    <button type="submit" class="btn btn-primary" style="width:auto; padding:0 24px;">Create event</button>
  </form>
</div>
@endslot

</x-layout>
