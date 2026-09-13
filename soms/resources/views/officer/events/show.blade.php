<x-layout :title="$event->title">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>{{ $event->title }}</h1>
    <p class="meta">{{ $event->date_start->format('M j') }} – {{ $event->date_end->format('M j, Y') }} &middot; {{ $event->venue ?: 'No venue set' }}</p>
  </div>
  <div>
    <span class="badge {{ $event->is_published ? 'approved' : 'pending' }}">{{ $event->is_published ? 'Published' : 'Draft' }}</span>
    @unless($event->is_published)
    <form method="POST" action="{{ route('officer.events.publish', $event) }}" style="display:inline;">
      @csrf
      <button class="btn btn-primary" style="width:auto; padding:0 16px; margin-left:8px;">Publish</button>
    </form>
    @endunless
  </div>
</div>

@if(session('status'))
<div class="banner" style="background:var(--emerald-soft); color:var(--emerald);">{{ session('status') }}</div>
@endif
@if(session('error'))
<div class="banner" style="background:var(--rose-soft); color:var(--rose);">{{ session('error') }}</div>
@endif

<div class="panel">
  <div class="panel-head"><h3>Event details</h3></div>
  <form method="POST" action="{{ route('officer.events.update', $event) }}" enctype="multipart/form-data" style="margin-bottom:14px;">
    @csrf
    @method('PATCH')
    <div class="field" style="margin-bottom:14px;">
      <label style="font-size:11px; color:var(--text-muted);">Cover image <span style="font-weight:400;">(JPG, PNG or WEBP, max 2MB)</span></label>
      <label for="eventImageInput" style="display:block; cursor:pointer; border:1px dashed var(--border-strong); border-radius:var(--radius-md); background:var(--surface-2); overflow:hidden; position:relative; max-width:260px;">
        <img id="eventImagePreview" src="{{ $event->image_url }}" alt="" style="display:{{ $event->image_url ? 'block' : 'none' }}; width:100%; height:140px; object-fit:cover;">
        <div id="eventImagePlaceholder" style="display:{{ $event->image_url ? 'none' : 'block' }}; padding:28px 12px; text-align:center; color:var(--text-faint); font-size:12.5px;">Click to upload a photo</div>
      </label>
      <input type="file" id="eventImageInput" name="image" accept="image/png,image/jpeg,image/webp" style="display:none;">
    </div>
    <div class="field" style="margin-bottom:14px;">
      <label style="font-size:11px; color:var(--text-muted);">Calendar color</label>
      <div style="display:flex; align-items:center; gap:12px;">
        <input type="color" name="color" id="colorInput" value="{{ old('color', $event->color ?? '#FF7A29') }}" style="width:44px; height:36px; padding:0; border:1px solid var(--border-strong); border-radius:8px; background:none; cursor:pointer;">
        <div style="display:flex; gap:6px;" id="colorPresets">
          @foreach(['#FF7A29','#8B7CF6','#1FC98D','#F5A623','#F5497A','#3B9EFF'] as $preset)
          <button type="button" class="color-swatch" data-color="{{ $preset }}" style="width:22px; height:22px; border-radius:50%; background:{{ $preset }}; border:2px solid var(--border-strong); cursor:pointer; padding:0;"></button>
          @endforeach
        </div>
      </div>
    </div>
    <div class="permission-grid" style="margin-bottom:14px;">
      <div class="field">
        <label style="font-size:11px; color:var(--text-muted);">Title</label>
        <input type="text" name="title" value="{{ old('title', $event->title) }}" class="field-input" style="width:100%;" required>
      </div>
      <div class="field">
        <label style="font-size:11px; color:var(--text-muted);">Type</label>
        <select name="type" class="field-input" style="width:100%;">
          @foreach($eventTypes as $value => $label)
          <option value="{{ $value }}" {{ old('type', $event->type) === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label style="font-size:11px; color:var(--text-muted);">Venue</label>
        <input type="text" name="venue" value="{{ old('venue', $event->venue) }}" class="field-input" style="width:100%;">
      </div>
    </div>
    <div class="field" style="margin-bottom:14px;">
      <label style="font-size:11px; color:var(--text-muted);">Description</label>
      <textarea name="description" class="field-input" style="width:100%; min-height:56px;">{{ old('description', $event->description) }}</textarea>
    </div>
    <button class="btn btn-ghost" style="width:auto; padding:0 16px;">Save details</button>
  </form>

  <div style="border-top:1px solid var(--border); padding-top:14px;">
    <form method="POST" action="{{ route('officer.events.destroy', $event) }}" onsubmit="return confirm('Delete &quot;{{ $event->title }}&quot; and everything under it (days, sessions, fine rules)? This can\'t be undone.');">
      @csrf
      @method('DELETE')
      <button class="mini-btn reject" style="width:auto;">Delete event</button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Fine amounts</h3></div>
  <form method="POST" action="{{ route('officer.events.fine-rules', $event) }}">
    @csrf
    <div class="permission-grid" style="margin-bottom:14px;">
      @foreach($event->fineRules as $rule)
      <div class="field">
        <label style="font-size:11px; color:var(--text-muted);">{{ ucwords(str_replace('_', ' ', $rule->violation_type)) }}</label>
        <input type="number" step="0.01" min="0" name="amounts[{{ $rule->violation_type }}]" value="{{ $rule->amount }}" class="field-input" style="width:100%;">
      </div>
      @endforeach
    </div>
    <button class="btn btn-ghost" style="width:auto; padding:0 16px;">Save amounts</button>
  </form>
</div>

@foreach($event->eventDays as $day)
<div class="panel">
  <div class="panel-head"><h3>{{ $day->date->format('l, M j, Y') }}</h3></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Session</th>
          <th>Time-in window</th>
          <th>Time-out window</th>
          <th>Fines issued</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($day->sessions as $session)
        <tr>
          <td>{{ ucfirst($session->session_type) }}</td>
          <td>{{ $session->timein_start->format('g:i A') }} – {{ $session->timein_end->format('g:i A') }}</td>
          <td>{{ $session->timeout_start ? $session->timeout_start->format('g:i A') . ' – ' . $session->timeout_end->format('g:i A') : '—' }}</td>
          <td><span class="badge {{ $session->fines_issued ? 'approved' : 'pending' }}">{{ $session->fines_issued ? 'Yes' : 'No' }}</span></td>
          <td class="queue-actions">
            <a href="{{ route('officer.attendance.station', $session) }}" class="mini-btn approve">Scan</a>
            <form method="POST" action="{{ route('officer.attendance.close', $session) }}" onsubmit="return confirm('Close this session and issue fines now?');">
              @csrf
              <button class="mini-btn reject">Close</button>
            </form>
          </td>
        </tr>
        <tr>
          <td colspan="5" style="padding:4px 8px 16px;">
            <div style="background:var(--surface-2); border-radius:var(--radius-md); padding:12px 14px;">
              <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:8px;">
                Delegates — {{ ucfirst($session->session_type) }}
              </div>
              @forelse($session->delegates as $delegate)
                <div class="queue-item" style="margin-bottom:6px;">
                  <div class="who">
                    <b>{{ $delegate->user->name }}</b>
                    <span>{{ $delegate->user->student_id }}</span>
                  </div>
                  <div class="queue-actions">
                    <form method="POST" action="{{ route('officer.delegates.destroy', [$session, $delegate]) }}" onsubmit="return confirm('Remove delegate access for {{ $delegate->user->name }}?');">
                      @csrf
                      @method('DELETE')
                      <button class="mini-btn reject">Remove</button>
                    </form>
                  </div>
                </div>
              @empty
                <p class="empty-note" style="margin-bottom:8px;">No delegates assigned — only Executive/Administrative officers can scan or override this session.</p>
              @endforelse
              <form method="POST" action="{{ route('officer.delegates.store', $session) }}" style="display:flex; gap:8px; margin-top:6px;">
                @csrf
                <input type="text" name="student_id" placeholder="Student ID (e.g. P1152302037)" maxlength="11" class="field-input" style="flex:1;" required>
                <button class="btn btn-ghost" style="width:auto; padding:0 14px;">Assign delegate</button>
              </form>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endforeach

<script>
document.addEventListener('DOMContentLoaded', function () {
  const imageInput = document.getElementById('eventImageInput');
  const imagePreview = document.getElementById('eventImagePreview');
  const imagePlaceholder = document.getElementById('eventImagePlaceholder');
  if (!imageInput) return;

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

  const colorInput = document.getElementById('colorInput');
  document.querySelectorAll('#colorPresets .color-swatch').forEach(function (btn) {
    btn.addEventListener('click', function () {
      colorInput.value = btn.dataset.color;
    });
  });
});
</script>
@endslot

</x-layout>
