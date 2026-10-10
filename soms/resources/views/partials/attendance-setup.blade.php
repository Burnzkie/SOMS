{{-- resources/views/partials/attendance-setup.blade.php --}}
{{-- "Attendance setup" selector + the matching session fields.
       full_day = Morning + Afternoon windows        (data-setup="full_day" blocks)
       short    = one session                         (data-setup="custom" block, one row)
       custom   = several named sessions              (data-setup="custom" block, add/remove rows)
     Any element on the form tagged data-setup="full_day" or data-setup="custom" (e.g. the parade
     picker) is shown/enabled only for its setup. Expects $timeWindow (full-day defaults, may be empty). --}}
@php
  $setupValue = old('attendance_setup', $setup ?? 'full_day');
  $sessionRows = old('custom_sessions', [['name' => '', 'timein_start' => '', 'timein_end' => '', 'timeout_start' => '', 'timeout_end' => '']]);
@endphp
<div class="field" style="margin-bottom:14px;">
  <label>Attendance setup <span style="font-weight:400; color:var(--text-muted);">(sessions repeat on every day of the event)</span></label>
  <select name="attendance_setup" id="attendance-setup" class="field-input" style="width:100%;">
    @foreach(['full_day' => 'Full-day event — Morning and Afternoon sessions', 'short' => 'Short event — one attendance session', 'custom' => 'Multiple activities — custom sessions'] as $value => $label)
    <option value="{{ $value }}" {{ $setupValue === $value ? 'selected' : '' }}>{{ $label }}</option>
    @endforeach
  </select>
</div>

<div data-setup="full_day">
  @include('partials.event-time-fields', ['timeWindow' => $timeWindow ?? []])
</div>

<div data-setup="custom" style="margin-bottom:14px;">
  <div data-custom-rows data-initial="{{ json_encode(array_values($sessionRows)) }}"></div>
  <button type="button" class="btn btn-ghost" data-add-session style="width:auto; height:32px; padding:0 14px; font-size:12px;">+ Add session</button>
  <p class="meta" style="margin:6px 0 0;">Time-in is required. Clear both time-out fields if students only need to sign in.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const select = document.getElementById('attendance-setup');
  if (!select) return;

  const form = select.closest('form');
  const rowsEl = form.querySelector('[data-custom-rows]');
  const addBtn = form.querySelector('[data-add-session]');
  const MAX_ROWS = 10;
  const initialRows = JSON.parse(rowsEl.dataset.initial || '[]');
  const initialSetup = select.value;
  let nextIndex = 0;

  // Builds one labelled input; values go through .value (never innerHTML) so typed text can't inject markup.
  function makeField(labelText, name, type, value, required) {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'flex:1; min-width:0;';
    const label = document.createElement('label');
    label.textContent = labelText;
    label.style.cssText = 'display:block; font-size:11px; color:var(--text-muted); margin-bottom:4px;';
    const input = document.createElement('input');
    input.type = type;
    input.name = name;
    input.value = value || '';
    input.required = !!required;
    input.className = 'field-input';
    input.style.width = '100%';
    if (type === 'text') input.maxLength = 60;
    wrap.appendChild(label);
    wrap.appendChild(input);
    return wrap;
  }

  function makePair(left, right) {
    const row = document.createElement('div');
    row.style.cssText = 'display:flex; gap:10px; margin-bottom:8px;';
    row.appendChild(left);
    row.appendChild(right);
    return row;
  }

  function addRow(values) {
    const v = values || {};
    const i = nextIndex++;
    const base = 'custom_sessions[' + i + ']';

    const row = document.createElement('div');
    row.setAttribute('data-session-row', '1');
    row.style.cssText = 'border:1px solid var(--border); border-radius:var(--radius-md); padding:10px 12px; margin-bottom:10px;';

    const head = document.createElement('div');
    head.style.cssText = 'display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;';
    const title = document.createElement('span');
    title.setAttribute('data-row-title', '1');
    title.style.cssText = 'font-size:12px; font-weight:600;';
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.setAttribute('data-remove', '1');
    remove.className = 'mini-btn reject';
    remove.textContent = 'Remove';
    remove.addEventListener('click', function () { row.remove(); applyMode(); });
    head.appendChild(title);
    head.appendChild(remove);
    row.appendChild(head);

    const nameField = makeField('Session name', base + '[name]', 'text', v.name, false);
    nameField.setAttribute('data-name-field', '1');
    nameField.style.marginBottom = '8px';
    nameField.firstChild.nextSibling.placeholder = 'e.g. Opening Program';
    row.appendChild(nameField);

    row.appendChild(makePair(
      makeField('Time-in from', base + '[timein_start]', 'time', v.timein_start, true),
      makeField('Time-in until', base + '[timein_end]', 'time', v.timein_end, true)
    ));
    row.appendChild(makePair(
      makeField('Time-out from (optional)', base + '[timeout_start]', 'time', v.timeout_start, false),
      makeField('Time-out until (optional)', base + '[timeout_end]', 'time', v.timeout_end, false)
    ));

    rowsEl.appendChild(row);
  }

  function resetRows() {
    rowsEl.innerHTML = '';
    nextIndex = 0;
    (initialRows.length ? initialRows : [{}]).forEach(addRow);
  }

  // Shows/enables only the blocks for the chosen setup; short = exactly one unnamed row.
  function applyMode() {
    const mode = select.value;
    const customActive = mode !== 'full_day';

    form.querySelectorAll('[data-setup]').forEach(function (el) {
      const active = el.dataset.setup === 'full_day' ? mode === 'full_day' : customActive;
      el.style.display = active ? '' : 'none';
      el.querySelectorAll('input, select, textarea').forEach(function (input) { input.disabled = !active; });
    });

    const rows = rowsEl.querySelectorAll('[data-session-row]');
    rows.forEach(function (row, index) {
      const hideRow = mode === 'short' && index > 0;
      row.style.display = hideRow ? 'none' : '';
      if (customActive) row.querySelectorAll('input').forEach(function (input) { input.disabled = hideRow; });
      row.querySelector('[data-name-field]').style.display = mode === 'short' ? 'none' : '';
      row.querySelector('[data-remove]').style.display = (mode === 'custom' && rows.length > 1) ? '' : 'none';
      row.querySelector('[data-row-title]').textContent = mode === 'short' ? 'Attendance session' : 'Session ' + (index + 1);
    });

    addBtn.style.display = (mode === 'custom' && rows.length < MAX_ROWS) ? '' : 'none';
  }

  addBtn.addEventListener('click', function () { addRow({}); applyMode(); });
  select.addEventListener('change', applyMode);

  // form.reset() (calendar quick-create) restores defaults; rebuild rows once it has run.
  form.addEventListener('reset', function () {
    setTimeout(function () { select.value = initialSetup; resetRows(); applyMode(); }, 0);
  });

  resetRows();
  applyMode();
});
</script>