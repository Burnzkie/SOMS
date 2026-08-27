<x-guest-layout title="Register">

@slot('content')
<h2>Create your account</h2>
<p class="sub">Register with your Student ID. Your account will need Admin approval before you can log in.</p>

@if ($errors->any())
  <div class="alert alert-error">Please fix the errors below.</div>
@endif

@php
  $academicPrograms = config('academic_programs', []);
@endphp

<form method="POST" action="{{ route('register') }}">
  @csrf

  <div class="field">
    <label for="name">Full name</label>
    <input type="text" id="name" name="name" placeholder="Juan Dela Cruz" value="{{ old('name') }}" required autofocus>
    @error('name')<div class="field-error">{{ $message }}</div>@enderror
  </div>

  <div class="field">
    <label for="student_id">Student ID</label>
    <input type="text" id="student_id" name="student_id" placeholder="P1152302037" maxlength="11" value="{{ old('student_id') }}" required>
    @error('student_id')<div class="field-error">{{ $message }}</div>@enderror
  </div>

  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" placeholder="juan.delacruz@pac.edu.ph" value="{{ old('email') }}" required>
    @error('email')<div class="field-error">{{ $message }}</div>@enderror
  </div>

  <div class="field-grid">
    <div class="field">
      <label for="department">Department</label>
      <select id="department" name="department" required>
        <option value="" disabled {{ old('department') ? '' : 'selected' }}>Select department</option>
        @foreach ($academicPrograms as $dept => $programs)
          <option value="{{ $dept }}" @selected(old('department') === $dept)>{{ $dept }}</option>
        @endforeach
      </select>
      @error('department')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
      <label for="program">Program</label>
      <select id="program" name="program" required>
        <option value="" disabled selected>Select department first</option>
      </select>
      @error('program')<div class="field-error">{{ $message }}</div>@enderror
    </div>
  </div>

  <div class="field">
    <label for="level">Year level</label>
    <select id="level" name="level" required>
      <option value="" disabled {{ old('level') ? '' : 'selected' }}>Select year level</option>
      @foreach (['1st Year', '2nd Year', '3rd Year', '4th Year'] as $lvl)
        <option value="{{ $lvl }}" @selected(old('level') === $lvl)>{{ $lvl }}</option>
      @endforeach
    </select>
    @error('level')<div class="field-error">{{ $message }}</div>@enderror
  </div>

  <button type="submit" class="btn btn-primary">Create account</button>
</form>

<p class="auth-foot">Already registered? <a href="{{ route('login') }}">Sign in</a></p>

<script>
  (function () {
    var academicPrograms = @json($academicPrograms);
    var oldDepartment = @json(old('department'));
    var oldProgram = @json(old('program'));
    var departmentSelect = document.getElementById('department');
    var programSelect = document.getElementById('program');

    function populatePrograms(department, selectedProgram) {
      programSelect.innerHTML = '';
      var programs = academicPrograms[department] || [];

      if (!programs.length) {
        var empty = document.createElement('option');
        empty.value = '';
        empty.disabled = true;
        empty.selected = true;
        empty.textContent = 'Select department first';
        programSelect.appendChild(empty);
        return;
      }

      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.disabled = true;
      placeholder.textContent = 'Select program';
      programSelect.appendChild(placeholder);

      var matched = false;
      programs.forEach(function (program) {
        var opt = document.createElement('option');
        opt.value = program;
        opt.textContent = program;
        if (selectedProgram === program) {
          opt.selected = true;
          matched = true;
        }
        programSelect.appendChild(opt);
      });

      placeholder.selected = !matched;
    }

    departmentSelect.addEventListener('change', function () {
      populatePrograms(departmentSelect.value, null);
    });

    // Re-populate on validation-error reload so the previously chosen
    // department/program pair survives a round trip.
    if (oldDepartment) {
      populatePrograms(oldDepartment, oldProgram);
    }
  })();
</script>
@endslot

</x-guest-layout>
