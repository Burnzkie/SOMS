<x-layout title="Calendar">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.11/index.global.min.css" rel="stylesheet">
<style>
#calendar{ background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:18px; box-shadow:var(--shadow-sm); }
.fc{ --fc-border-color:var(--border); --fc-page-bg-color:transparent; --fc-neutral-bg-color:var(--surface-2); color:var(--text); font-family:var(--font-ui); }
.fc .fc-toolbar-title{ font-family:var(--font-display); font-size:18px; }
.fc .fc-button{ background:var(--surface-2); border:1px solid var(--border); color:var(--text); box-shadow:none; text-transform:capitalize; }
.fc .fc-button:hover{ background:var(--primary-soft); color:var(--primary); }
.fc .fc-button-primary:not(:disabled).fc-button-active{ background:var(--primary); border-color:var(--primary); color:#fff; }
.fc-daygrid-day.fc-day-today{ background:var(--primary-soft) !important; }
.fc-event{ cursor:pointer; }
.legend{ display:flex; gap:18px; margin-bottom:14px; font-size:12.5px; color:var(--text-muted); }
.legend .dot{ display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:6px; }
.qc-backdrop{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1000; align-items:center; justify-content:center; padding:20px; }
.qc-backdrop.open{ display:flex; }
.qc-modal{ background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:22px; width:100%; max-width:440px; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow-sm); }
.qc-modal h3{ margin:0 0 4px; font-family:var(--font-display); }
.qc-modal .meta{ margin:0 0 16px; }
.qc-field{ margin-bottom:12px; }
.qc-field label{ display:block; margin-bottom:4px; font-size:13px; font-weight:500; }
.qc-row{ display:flex; gap:10px; }
.qc-row .qc-field{ flex:1; }
.qc-end-preview{ font-size:12.5px; color:var(--text-muted); margin-top:4px; }
.qc-actions{ display:flex; justify-content:flex-end; gap:8px; margin-top:16px; }
</style>
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Calendar</h1>
    <p class="meta">SOMS events for your organization.</p>
  </div>
</div>

@if(session('status'))
<div class="banner" style="background:var(--emerald-soft); color:var(--emerald);">{{ session('status') }}</div>
@endif

<div class="legend">
  <span><span class="dot" style="background:#FF7A29;"></span>SOMS Events</span>
</div>

<div id="calendar"></div>

@if($canManageEvents)
<!-- Quick-create: opened by clicking an empty date on the calendar. Posts
     the same shape as the full /officer/events/create form (title,
     description, venue, type, date_start, date_end, has_parade) — "days"
     is a UI-only stepper here, computed into date_end before submit. -->
<div class="qc-backdrop" id="qc-backdrop">
  <div class="qc-modal">
    <h3>New event</h3>
    <p class="meta" id="qc-date-label">—</p>
    <form method="POST" action="{{ route('officer.events.store') }}" id="qc-form" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="source" value="calendar">
      <input type="hidden" name="date_start" id="qc-date-start">
      <input type="hidden" name="date_end" id="qc-date-end">

      <div class="qc-field">
        <label>Cover image <span style="font-weight:400; color:var(--text-muted);">(optional)</span></label>
        <label for="qc-image-input" style="display:block; cursor:pointer; border:1px dashed var(--border-strong); border-radius:var(--radius-md); background:var(--surface-2); overflow:hidden; position:relative;">
          <img id="qc-image-preview" src="" alt="" style="display:none; width:100%; max-height:140px; object-fit:cover;">
          <div id="qc-image-placeholder" style="padding:18px 12px; text-align:center; color:var(--text-faint); font-size:12px;">Click to upload a photo</div>
        </label>
        <input type="file" id="qc-image-input" name="image" accept="image/png,image/jpeg,image/webp" style="display:none;">
      </div>

      <div class="qc-field">
        <label>Calendar color</label>
        <div style="display:flex; align-items:center; gap:10px;">
          <input type="color" name="color" id="qc-color-input" value="#FF7A29" style="width:38px; height:32px; padding:0; border:1px solid var(--border-strong); border-radius:8px; background:none; cursor:pointer;">
          <div style="display:flex; gap:6px;" id="qc-color-presets">
            @foreach(['#FF7A29','#8B7CF6','#1FC98D','#F5A623','#F5497A','#3B9EFF'] as $preset)
            <button type="button" class="qc-color-swatch" data-color="{{ $preset }}" style="width:20px; height:20px; border-radius:50%; background:{{ $preset }}; border:2px solid var(--border-strong); cursor:pointer; padding:0;"></button>
            @endforeach
          </div>
        </div>
      </div>

      <div class="qc-field">
        <label>Event type</label>
        <select name="type" id="qc-type" class="field-input" style="width:100%;">
          @foreach(json_decode($eventTypesJson, true) as $value => $t)
          <option value="{{ $value }}">{{ $t['emoji'] }} {{ $t['label'] }}</option>
          @endforeach
        </select>
      </div>

      <div class="qc-field" id="qc-activity-field" style="display:none;">
        <label>Foundation Day activity <span style="font-weight:400; color:var(--text-muted);">(optional — prefills the title)</span></label>
        <select id="qc-activity" class="field-input" style="width:100%;">
          <option value="">— Select an activity —</option>
          @foreach(json_decode($foundationDayActivitiesJson, true) as $activity)
          <option value="{{ $activity }}">{{ $activity }}</option>
          @endforeach
        </select>
      </div>

      <div class="qc-field">
        <label>Title</label>
        <input type="text" name="title" id="qc-title" class="field-input" style="width:100%;" required>
      </div>

      <div class="qc-row">
        <div class="qc-field">
          <label>Days</label>
          <input type="number" id="qc-days" class="field-input" style="width:100%;" value="1" min="1" max="30" required>
        </div>
        <div class="qc-field">
          <label>Venue</label>
          <input type="text" name="venue" class="field-input" style="width:100%;">
        </div>
      </div>
      <p class="qc-end-preview" id="qc-end-preview"></p>

      <div class="qc-field">
        <label>Description <span style="font-weight:400; color:var(--text-muted);">(optional)</span></label>
        <textarea name="description" class="field-input" style="width:100%; min-height:56px;"></textarea>
      </div>

      <div class="qc-field">
        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
          <input type="checkbox" name="has_parade" value="1"> Include a Parade session (time-in only) on each day
        </label>
      </div>

      <div class="qc-actions">
        <button type="button" class="btn btn-ghost" style="width:auto; padding:0 16px;" id="qc-cancel">Cancel</button>
        <button type="submit" class="btn btn-primary" style="width:auto; padding:0 16px;">Create event</button>
      </div>
    </form>
  </div>
</div>
@endif

<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.11/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('calendar');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  const canManageEvents = @json($canManageEvents);

  // Local (not UTC) YYYY-MM-DD — used everywhere a date gets sent to the
  // server. Do NOT use Date#toISOString() for this: it converts to UTC
  // first, which rolls a local midnight back a day in any positive-UTC-
  // offset timezone (e.g. PHT, UTC+8) — that was the cause of "drag to
  // the 14th, refresh, it's on the 13th".
  function toDateInputValue(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  // Drag a card to reschedule it — shifts the event's whole multi-day
  // range (and its generated EventDays/sessions) rather than deleting
  // and re-creating it.
  async function handleDrop(info) {
    const id = info.event.id.replace('event-', '');

    // Roadmap Phase 3.1 — error handling here was already correct
    // (revert on failure); the only gap was no visual cue that the
    // reschedule was in flight on a slow connection. Dim the dragged
    // card until the request settles.
    const eventEl = document.querySelector(`[data-event-id="event-${id}"]`) || info.el;
    if (eventEl) eventEl.style.opacity = '0.5';

    try {
      const response = await fetch(`/officer/events/${id}/reschedule`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ date_start: toDateInputValue(info.event.start) }),
      });

      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        alert(payload.message || 'Could not reschedule — reverting.');
        info.revert();
      }
    } catch (err) {
      alert('Network error — reverting.');
      info.revert();
    } finally {
      if (eventEl) eventEl.style.opacity = '';
    }
  }

  // Click a card -> go manage it (title/venue/type, sessions, fine
  // rules, delete) on its detail page.
  function handleEventClick(info) {
    info.jsEvent.preventDefault();
    const id = info.event.id.replace('event-', '');
    window.location.href = `/officer/events/${id}`;
  }

  // --- Quick-create modal: click an empty day -> auto-create an event ---
  // Set up (and openQuickCreate defined) before the calendar itself is
  // constructed, since the calendar's dateClick option references it.
  let openQuickCreate;
  if (canManageEvents) {
    const eventTypes = {!! $eventTypesJson !!};
    const backdrop = document.getElementById('qc-backdrop');
    const form = document.getElementById('qc-form');
    const dateLabel = document.getElementById('qc-date-label');
    const dateStartInput = document.getElementById('qc-date-start');
    const dateEndInput = document.getElementById('qc-date-end');
    const typeSelect = document.getElementById('qc-type');
    const activityField = document.getElementById('qc-activity-field');
    const activitySelect = document.getElementById('qc-activity');
    const titleInput = document.getElementById('qc-title');
    const daysInput = document.getElementById('qc-days');
    const endPreview = document.getElementById('qc-end-preview');
    const imageInput = document.getElementById('qc-image-input');
    const imagePreview = document.getElementById('qc-image-preview');
    const imagePlaceholder = document.getElementById('qc-image-placeholder');
    const colorInput = document.getElementById('qc-color-input');
    let clickedDate = null;

    document.querySelectorAll('#qc-color-presets .qc-color-swatch').forEach(function (btn) {
      btn.addEventListener('click', function () {
        colorInput.value = btn.dataset.color;
      });
    });

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

    openQuickCreate = function (info) {
      clickedDate = info.date; // local midnight of the clicked cell
      dateLabel.textContent = clickedDate.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
      form.reset();
      daysInput.value = 1;
      imagePreview.src = '';
      imagePreview.style.display = 'none';
      imagePlaceholder.style.display = 'block';
      colorInput.value = '#FF7A29';
      syncActivityVisibility();
      updateEndPreview();
      backdrop.classList.add('open');
      titleInput.focus();
    }

    function closeQuickCreate() {
      backdrop.classList.remove('open');
    }

    function syncActivityVisibility() {
      activityField.style.display = typeSelect.value === 'foundation_day' ? 'block' : 'none';
    }

    function updateEndPreview() {
      if (!clickedDate) return;
      const days = Math.max(1, parseInt(daysInput.value, 10) || 1);
      const end = new Date(clickedDate);
      end.setDate(end.getDate() + (days - 1));
      dateStartInput.value = toDateInputValue(clickedDate);
      dateEndInput.value = toDateInputValue(end);
      endPreview.textContent = days === 1
        ? 'Single day: ' + toDateInputValue(clickedDate)
        : 'Runs ' + toDateInputValue(clickedDate) + ' through ' + toDateInputValue(end) + ' (' + days + ' days)';
    }

    typeSelect.addEventListener('change', function () {
      syncActivityVisibility();
      const preset = eventTypes[typeSelect.value];
      if (preset && !titleInput.value.trim() && typeSelect.value !== 'other' && typeSelect.value !== 'foundation_day') {
        titleInput.value = preset.label;
      }
    });

    activitySelect.addEventListener('change', function () {
      if (activitySelect.value) {
        titleInput.value = 'Foundation Day — ' + activitySelect.value;
      }
    });

    daysInput.addEventListener('input', updateEndPreview);
    document.getElementById('qc-cancel').addEventListener('click', closeQuickCreate);
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) closeQuickCreate();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && backdrop.classList.contains('open')) closeQuickCreate();
    });
    form.addEventListener('submit', updateEndPreview); // final sync in case of a stray edit
  }

  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    height: 'auto',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
    events: {!! $eventsJson !!},
    editable: canManageEvents,
    eventStartEditable: true,
    eventDurationEditable: false,
    eventDrop: handleDrop,
    eventClick: handleEventClick,
    dateClick: canManageEvents ? openQuickCreate : undefined,
  });
  calendar.render();
});
</script>
@endslot

</x-layout>
