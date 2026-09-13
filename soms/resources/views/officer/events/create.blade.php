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
  <form method="POST" action="{{ route('officer.events.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="field" style="margin-bottom:14px;">
      <label>Cover image <span style="font-weight:400; color:var(--text-muted);">(optional — JPG, PNG or WEBP, max 2MB)</span></label>
      <label for="eventImageInput" style="display:block; cursor:pointer; border:1px dashed var(--border-strong); border-radius:var(--radius-md); background:var(--surface-2); overflow:hidden; position:relative;">
        <img id="eventImagePreview" src="" alt="" style="display:none; width:100%; max-height:220px; object-fit:cover;">
        <div id="eventImagePlaceholder" style="padding:28px 12px; text-align:center; color:var(--text-faint); font-size:12.5px;">Click to upload a photo for this event</div>
      </label>
      <input type="file" id="eventImageInput" name="image" accept="image/png,image/jpeg,image/webp" style="display:none;">
    </div>
    <div class="field" style="margin-bottom:14px;">
      <label>Calendar color <span style="font-weight:400; color:var(--text-muted);">(how this event appears on the calendar)</span></label>
      <div style="display:flex; align-items:center; gap:12px;">
        <input type="color" name="color" id="colorInput" value="{{ old('color', '#FF7A29') }}" style="width:44px; height:36px; padding:0; border:1px solid var(--border-strong); border-radius:8px; background:none; cursor:pointer;">
        <div style="display:flex; gap:6px;" id="colorPresets">
          @foreach(['#FF7A29','#8B7CF6','#1FC98D','#F5A623','#F5497A','#3B9EFF'] as $preset)
          <button type="button" class="color-swatch" data-color="{{ $preset }}" style="width:22px; height:22px; border-radius:50%; background:{{ $preset }}; border:2px solid var(--border-strong); cursor:pointer; padding:0;"></button>
          @endforeach
        </div>
      </div>
    </div>
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
        <select name="type" id="type" class="field-input" style="width:100%;">
          @foreach($eventTypes as $value => $label)
          <option value="{{ $value }}" {{ old('type', 'other') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
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
    <div class="field" id="activity-field" style="margin-bottom:14px; display:none;">
      <label>Foundation Day activity <span style="font-weight:400; color:var(--text-muted);">(optional — prefills the title)</span></label>
      <select id="activity" class="field-input" style="width:100%;">
        <option value="">— Select an activity —</option>
        @foreach($foundationDayActivities as $activity)
        <option value="{{ $activity }}">{{ $activity }}</option>
        @endforeach
      </select>
    </div>
    <div class="field" style="margin-bottom:20px;">
      <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
        <input type="checkbox" name="has_parade" value="1"> Include a Parade session (time-in only) on each day
      </label>
    </div>
    <button type="submit" class="btn btn-primary" style="width:auto; padding:0 24px;">Create event</button>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const typeSelect = document.getElementById('type');
  const activityField = document.getElementById('activity-field');
  const activitySelect = document.getElementById('activity');
  const titleInput = document.querySelector('input[name="title"]');

  function syncActivityVisibility() {
    activityField.style.display = typeSelect.value === 'foundation_day' ? 'block' : 'none';
  }

  typeSelect.addEventListener('change', syncActivityVisibility);
  activitySelect.addEventListener('change', function () {
    if (activitySelect.value && !titleInput.value.trim()) {
      titleInput.value = 'Foundation Day — ' + activitySelect.value;
    }
  });

  syncActivityVisibility();

  const colorInput = document.getElementById('colorInput');
  document.querySelectorAll('#colorPresets .color-swatch').forEach(function (btn) {
    btn.addEventListener('click', function () {
      colorInput.value = btn.dataset.color;
    });
  });

  const imageInput = document.getElementById('eventImageInput');
  const imagePreview = document.getElementById('eventImagePreview');
  const imagePlaceholder = document.getElementById('eventImagePlaceholder');

  imageInput.addEventListener('change', function () {
    const file = imageInput.files && imageInput.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (e) {
      imagePreview.src = e.target.result;
      imagePreview.style.display = 'block';
      imagePlaceholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
  });
});
</script>
@endslot

</x-layout>
