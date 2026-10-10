{{-- resources/views/partials/parade-date-picker.blade.php --}}
{{-- Per-date parade picker. On a selected date the Morning time-in is the parade check-in
     (higher 'missed_parade' fine). $fixedDates = list of Y-m-d (show page) or null to build the
     list from the form's date_start/date_end inputs (create page). $selected = preselected dates. --}}
@php
  $pickerId = $pickerId ?? 'parade';
  $fixedDates = $fixedDates ?? null;
  // After a failed validation, restore what the officer ticked instead of the saved state.
  $selectedDates = old('parade_dates_submitted') ? old('parade_dates', []) : ($selected ?? []);
@endphp
<div class="field" id="{{ $pickerId }}-picker" style="margin-bottom:14px;"
     data-fixed="{{ json_encode($fixedDates) }}" data-selected="{{ json_encode(array_values($selectedDates)) }}">
  {{-- Always sent, so "everything unticked" is distinguishable from "field not on this form" --}}
  <input type="hidden" name="parade_dates_submitted" value="1">
  <label>Parade dates <span style="font-weight:400; color:var(--text-muted);">(optional — Morning time-in on a ticked date is the parade check-in)</span></label>
  <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:8px;">
    @foreach(['none' => 'None', 'first' => 'First day', 'last' => 'Last day', 'all' => 'All days'] as $mode => $modeLabel)
    <button type="button" class="btn btn-ghost" data-parade-preset="{{ $mode }}" style="width:auto; height:30px; padding:0 12px; font-size:12px;">{{ $modeLabel }}</button>
    @endforeach
  </div>
  <div data-parade-boxes style="display:flex; flex-wrap:wrap; gap:8px 16px;"></div>
  <p class="meta" data-parade-empty style="display:none; margin:0;">Pick the start and end dates to choose parade days.</p>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const root = document.getElementById('{{ $pickerId }}-picker');
  if (!root) return;

  const boxesEl = root.querySelector('[data-parade-boxes]');
  const emptyEl = root.querySelector('[data-parade-empty]');
  const fixedDates = JSON.parse(root.dataset.fixed || 'null');
  const form = root.closest('form');
  const startInput = form.querySelector('[name="date_start"]');
  const endInput = form.querySelector('[name="date_end"]');
  const MAX_DAYS = 31;
  const selected = new Set(JSON.parse(root.dataset.selected || '[]'));

  // YYYY-MM-DD list from start to end. UTC math avoids local-timezone day shifts.
  function datesBetween(start, end) {
    if (!start || !end || end < start) return [];
    const result = [];
    const cursor = new Date(start + 'T00:00:00Z');
    const last = new Date(end + 'T00:00:00Z');
    while (cursor <= last && result.length < MAX_DAYS) {
      result.push(cursor.toISOString().slice(0, 10));
      cursor.setUTCDate(cursor.getUTCDate() + 1);
    }
    return result;
  }

  function currentDates() {
    return fixedDates || datesBetween(startInput && startInput.value, endInput && endInput.value);
  }

  function labelFor(dateStr) {
    return new Date(dateStr + 'T00:00:00Z').toLocaleDateString('en-US', { month: 'short', day: 'numeric', weekday: 'short', timeZone: 'UTC' });
  }

  // Rebuilds the checkboxes; ticks that no longer exist in the range are dropped.
  function render() {
    const dates = currentDates();
    boxesEl.innerHTML = '';
    emptyEl.style.display = dates.length ? 'none' : 'block';

    Array.from(selected).forEach(function (d) { if (!dates.includes(d)) selected.delete(d); });

    dates.forEach(function (dateStr) {
      const label = document.createElement('label');
      label.style.cssText = 'display:flex; align-items:center; gap:6px; font-size:13px; font-weight:500;';
      const box = document.createElement('input');
      box.type = 'checkbox';
      box.name = 'parade_dates[]';
      box.value = dateStr;
      box.checked = selected.has(dateStr);
      box.addEventListener('change', function () {
        if (box.checked) selected.add(dateStr); else selected.delete(dateStr);
      });
      label.appendChild(box);
      label.appendChild(document.createTextNode(labelFor(dateStr)));
      boxesEl.appendChild(label);
    });
  }

  root.querySelectorAll('[data-parade-preset]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const dates = currentDates();
      selected.clear();
      const mode = btn.dataset.paradePreset;
      if (mode === 'first' && dates.length) selected.add(dates[0]);
      if (mode === 'last' && dates.length) selected.add(dates[dates.length - 1]);
      if (mode === 'all') dates.forEach(function (d) { selected.add(d); });
      render();
    });
  });

  [startInput, endInput].forEach(function (input) {
    if (input) input.addEventListener('change', render);
  });

  render();
});
</script>