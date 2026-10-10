{{-- resources/views/partials/custom-sessions-edit.blade.php --}}
{{-- Edit form for the sessions of a short / multiple-activities event. Rows are keyed by sort_order and
     apply to every day. Adding or removing sessions isn't supported here — recreate the event for that.
     Expects $customSessions: [sort_order => name + four 'H:i' times]. --}}
@php
  $sessionRows = old('custom_sessions', $customSessions);
@endphp
<div class="field" style="margin-bottom:14px;">
  <label style="font-size:11px; color:var(--text-muted);">Sessions <span style="font-weight:400;">(times apply to every day; to add or remove sessions, create a new event)</span></label>
  @foreach($sessionRows as $order => $row)
  <fieldset style="border:1px solid var(--border); border-radius:var(--radius-md); padding:10px 12px 12px; margin:0 0 12px;">
    <legend style="font-size:12px; font-weight:600; padding:0 6px;">Session {{ $loop->iteration }}</legend>
    <div style="margin-bottom:8px;">
      <label style="display:block; font-size:11px; color:var(--text-muted); margin-bottom:4px;">Name</label>
      <input type="text" name="custom_sessions[{{ $order }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="60" class="field-input" style="width:100%;">
    </div>
    <div style="display:flex; gap:10px; margin-bottom:8px;">
      <div style="flex:1;">
        <label style="display:block; font-size:11px; color:var(--text-muted); margin-bottom:4px;">Time-in from</label>
        <input type="time" name="custom_sessions[{{ $order }}][timein_start]" value="{{ $row['timein_start'] ?? '' }}" required class="field-input" style="width:100%;">
      </div>
      <div style="flex:1;">
        <label style="display:block; font-size:11px; color:var(--text-muted); margin-bottom:4px;">Time-in until</label>
        <input type="time" name="custom_sessions[{{ $order }}][timein_end]" value="{{ $row['timein_end'] ?? '' }}" required class="field-input" style="width:100%;">
      </div>
    </div>
    <div style="display:flex; gap:10px;">
      <div style="flex:1;">
        <label style="display:block; font-size:11px; color:var(--text-muted); margin-bottom:4px;">Time-out from (optional)</label>
        <input type="time" name="custom_sessions[{{ $order }}][timeout_start]" value="{{ $row['timeout_start'] ?? '' }}" class="field-input" style="width:100%;">
      </div>
      <div style="flex:1;">
        <label style="display:block; font-size:11px; color:var(--text-muted); margin-bottom:4px;">Time-out until (optional)</label>
        <input type="time" name="custom_sessions[{{ $order }}][timeout_end]" value="{{ $row['timeout_end'] ?? '' }}" class="field-input" style="width:100%;">
      </div>
    </div>
  </fieldset>
  @endforeach
</div>