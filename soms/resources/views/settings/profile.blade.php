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

<p class="auth-foot" style="margin-top:16px; text-align:left;">
  <a href="{{ route('change-password.show') }}">Change password →</a>
</p>

<style>
/* Scoped to this page — .field/.field-error/.alert/.auth-foot only exist
   in guest.blade.php's <style> (login/register/change-password), not in
   the app-shell layout this page uses, so they're redefined here rather
   than left unstyled. Same design tokens (--primary, --rose, etc.) as
   the rest of the app, so it matches visually. */
.field{ margin-bottom:16px; }
.field label{
  display:block; font-size:12px; font-weight:600;
  color:var(--text-muted); margin-bottom:6px;
}
.field input{
  width:100%; height:42px; border-radius:var(--radius-sm);
  border:1px solid var(--border-strong); background:var(--surface-2);
  color:var(--text); padding:0 12px; font-size:13px;
  font-family:var(--font-ui); outline:none; transition:border-color .2s;
}
.field input:focus{ border-color:var(--primary); }
.field input::placeholder{ color:var(--text-faint); }
.field-error{ color:var(--rose); font-size:12px; margin-top:6px; }

.alert{
  padding:12px 14px; border-radius:var(--radius-md);
  font-size:12.5px; margin-bottom:18px;
}
.alert-success{
  background:rgba(31,201,141,.14); color:var(--emerald);
  border:1px solid rgba(31,201,141,.3);
}
.alert-error{
  background:rgba(245,73,122,.14); color:var(--rose);
  border:1px solid rgba(245,73,122,.3);
}

.auth-foot{ font-size:12px; color:var(--text-muted); }
.auth-foot a{ color:var(--primary); font-weight:600; }
</style>

@endslot

</x-layout>
