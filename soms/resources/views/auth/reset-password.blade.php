<x-guest-layout title="Reset password">

@slot('content')
<h2>Set a new password</h2>
<p class="sub">Choose a new password for your SOMS account.</p>

@if ($errors->any())
  <div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('reset-password.update') }}">
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" placeholder="you@example.com" value="{{ old('email', $email) }}" required autofocus>
  </div>
  <div class="field">
    <label for="password">New password</label>
    <input type="password" id="password" name="password" placeholder="••••••••" required>
  </div>
  <div class="field">
    <label for="password_confirmation">Confirm new password</label>
    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="••••••••" required>
  </div>
  <p style="font-size:11.5px; color:var(--text-faint); margin-bottom:16px;">Minimum 8 characters, with upper &amp; lowercase letters and at least one number.</p>
  <button type="submit" class="btn btn-primary">Reset password</button>
</form>

<p class="auth-foot"><a href="{{ route('login') }}">Back to sign in</a></p>
@endslot

</x-guest-layout>
