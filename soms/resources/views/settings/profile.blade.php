<x-layout title="Edit Profile">

@slot('nav')
@include('partials.' . auth()->user()->role . '-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Edit Profile</h1>
    <p class="meta">Update your personal information</p>
  </div>
</div>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
  <div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<div class="stat-card" style="max-width:520px;">
  <form method="POST" action="{{ route('settings.profile.update') }}">
    @csrf
    @method('PUT')

    <div class="field">
      <label for="name">Full name</label>
      <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
      @error('name')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
      @error('email')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
      <label for="department">Department</label>
      <input type="text" id="department" name="department" value="{{ old('department', $user->department) }}">
      @error('department')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
      <label for="program">Program</label>
      <input type="text" id="program" name="program" value="{{ old('program', $user->program) }}">
      @error('program')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
      <label for="level">Year level</label>
      <input type="text" id="level" name="level" value="{{ old('level', $user->level) }}">
      @error('level')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <button type="submit" class="btn btn-primary">Save Changes</button>
  </form>
</div>

<p class="auth-foot" style="margin-top:16px;">
  <a href="{{ route('change-password.show') }}">Change password →</a>
</p>

@endslot

</x-layout>
