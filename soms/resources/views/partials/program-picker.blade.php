{{-- resources/views/partials/program-picker.blade.php --}}
{{-- Which programs must attend this event. Nothing ticked = every program is required.
     $selected = preselected program names. Programs come from config/academic_programs.php, grouped by department.
     Guard: if nothing is ticked, a live warning shows and saving asks for confirmation (see script at the bottom). --}}
@php
  // After a failed validation, restore what the officer ticked instead of the saved state.
  $selectedPrograms = old('target_programs_submitted') ? old('target_programs', []) : ($selected ?? []);
  $programGroups = \App\Services\EventSetupService::programGroups();

  // A previously-saved program that's no longer in any group stays visible so it can be unticked.
  $groupedPrograms = array_merge(...array_values($programGroups));
  $orphaned = array_values(array_diff($selectedPrograms, $groupedPrograms));
  if (!empty($orphaned)) {
      $programGroups['Other'] = array_values(array_unique(array_merge($programGroups['Other'] ?? [], $orphaned)));
  }
@endphp
{{-- data-program-picker lets the guard script find this picker inside any form that includes it --}}
<div class="field" style="margin-bottom:14px;" data-program-picker>
  {{-- Always sent, so "nothing ticked" (= all programs) is distinguishable from "field not on this form" --}}
  <input type="hidden" name="target_programs_submitted" value="1">
  <label>Required programs <span style="font-weight:400; color:var(--text-muted);">(optional — leave all unticked if every program must attend; others won't be fined)</span></label>
  <div style="max-height:220px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius-md); padding:10px 12px;">
    @forelse($programGroups as $department => $programs)
    <div style="margin-bottom:10px;">
      <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:4px;">{{ $department }}</div>
      <div style="display:flex; flex-direction:column; gap:4px;">
        @foreach($programs as $program)
        <label style="display:flex; align-items:center; gap:6px; font-size:13px; font-weight:500;">
          <input type="checkbox" name="target_programs[]" value="{{ $program }}" {{ in_array($program, $selectedPrograms, true) ? 'checked' : '' }}>
          {{ $program }}
        </label>
        @endforeach
      </div>
    </div>
    @empty
    <span class="meta" style="margin:0;">No programs found.</span>
    @endforelse
  </div>

  {{-- Live warning: visible only while nothing is ticked (hidden attribute is toggled by the script) --}}
  <div data-program-warning class="meta" style="margin-top:6px; color:var(--amber, #b45309);" @if(!empty($selectedPrograms)) hidden @endif>
    ⚠ No program selected — <strong>every program</strong> will be required and fined for absences.
  </div>
</div>

{{-- Rendered once per page even if this partial is included in several forms (create, calendar modal, show) --}}
@once
<script>
// Guard against accidentally requiring ALL programs: shows a live warning and asks before saving.
(function () {
  function hasTickedProgram(picker) {
    return picker.querySelector('input[name="target_programs[]"]:checked') !== null;
  }

  // Keep the inline warning in sync as the officer ticks/unticks programs
  document.addEventListener('change', function (e) {
    if (!e.target.matches('input[name="target_programs[]"]')) { return; }
    var picker = e.target.closest('[data-program-picker]');
    picker.querySelector('[data-program-warning]').hidden = hasTickedProgram(picker);
  });

  // Confirm before submitting a form whose picker has nothing ticked
  document.addEventListener('submit', function (e) {
    var picker = e.target.querySelector('[data-program-picker]');
    if (!picker || hasTickedProgram(picker)) { return; }

    var proceed = confirm(
      'No program is selected.\n\nEVERY program will be required to attend, and all absent students will be fined.\n\nSave anyway?'
    );
    if (!proceed) { e.preventDefault(); }
  });
})();
</script>
@endonce