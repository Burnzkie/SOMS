<x-guest-layout title="Forgot password">

@slot('content')
<h2>Reset your password</h2>
<p class="sub">Enter the email on your account and we'll send you a reset link.</p>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
  <div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('forgot-password.send') }}">
  @csrf
  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" placeholder="you@example.com" value="{{ old('email') }}" required autofocus>
  </div>
  <button type="submit" class="btn btn-primary">Send reset link</button>
</form>

<p class="auth-foot">Remembered it? <a href="{{ route('login') }}">Back to sign in</a></p>
@endslot

</x-guest-layout>
