{{-- resources/views/partials/program-picker.blade.php --}}
{{-- Which programs must attend this event. Nothing ticked = every program is required.
     $selected = preselected program names. Programs come from config/academic_programs.php, grouped by department. --}}
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
<div class="field" style="margin-bottom:14px;">
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
</div>