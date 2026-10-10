{{-- resources/views/partials/event-time-fields.blade.php --}}
{{-- Morning + Afternoon time-in / time-out windows. Time-in is required; time-out is optional
     (blank = that session has no time-out). Expects $timeWindow (flat 8-key 'H:i' array);
     falls back to the service defaults when empty. Old input wins on validation errors. --}}
@php
  $window = array_filter($timeWindow ?? []) ? $timeWindow : \App\Services\EventSetupService::defaultTimeWindow();
  $timeValue = fn (string $field) => old($field, $window[$field] ?? '');
  $periods = ['morning' => 'Morning', 'afternoon' => 'Afternoon'];
@endphp
@foreach($periods as $key => $label)
<fieldset style="border:1px solid var(--border); border-radius:var(--radius-md); padding:10px 12px 12px; margin:0 0 14px;">
  <legend style="font-size:12px; font-weight:600; padding:0 6px;">{{ $label }} session</legend>
  <div style="margin-bottom:8px;">
    <label style="display:block; font-size:11px; color:var(--text-muted); margin-bottom:4px;">Time-in window</label>
    <div style="display:flex; gap:10px;">
      <input type="time" name="{{ $key }}_timein_start" value="{{ $timeValue($key . '_timein_start') }}" required class="field-input" style="flex:1;" aria-label="{{ $label }} time-in start">
      <input type="time" name="{{ $key }}_timein_end" value="{{ $timeValue($key . '_timein_end') }}" required class="field-input" style="flex:1;" aria-label="{{ $label }} time-in end">
    </div>
  </div>
  <div>
    <label style="display:block; font-size:11px; color:var(--text-muted); margin-bottom:4px;">Time-out window <span style="font-weight:400;">(optional — clear both to skip time-out)</span></label>
    <div style="display:flex; gap:10px;">
      <input type="time" name="{{ $key }}_timeout_start" value="{{ $timeValue($key . '_timeout_start') }}" class="field-input" style="flex:1;" aria-label="{{ $label }} time-out start">
      <input type="time" name="{{ $key }}_timeout_end" value="{{ $timeValue($key . '_timeout_end') }}" class="field-input" style="flex:1;" aria-label="{{ $label }} time-out end">
    </div>
  </div>
</fieldset>
@endforeach