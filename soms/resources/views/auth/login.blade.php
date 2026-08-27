<x-guest-layout title="Sign in">

@slot('content')
<h2>Welcome back</h2>
<p class="sub">Sign in with your Student ID to access SOMS.</p>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
  <div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('login') }}">
  @csrf
  <div class="field">
    <label for="student_id">Student ID</label>
    <input type="text" id="student_id" name="student_id" placeholder="P1152302037"
           value="{{ old('student_id') }}" maxlength="11" required autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <div class="password-wrap">
      <input type="password" id="password" name="password" placeholder="••••••••" required>
      <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password" tabindex="-1">
        <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
      </button>
    </div>
  </div>
  <button type="submit" class="btn btn-primary">Sign in</button>
</form>

<p class="auth-foot">
  <a href="{{ route('forgot-password.show') }}">Forgot password?</a>
</p>
<p class="auth-foot">No account yet? <a href="{{ route('register') }}">Register here</a></p>

<style>
.password-wrap{ position:relative; }
.password-wrap input{ padding-right:44px; }
.password-toggle{
  position:absolute; right:6px; top:50%; transform:translateY(-50%);
  width:34px; height:34px; border:none; background:transparent; color:var(--text-faint);
  display:flex; align-items:center; justify-content:center; cursor:pointer; border-radius:8px;
  transition:color .15s var(--ease), background .15s var(--ease);
}
.password-toggle:hover{ color:var(--text); background:rgba(255,255,255,.06); }
</style>

<script>
(function(){
  const toggle = document.getElementById('passwordToggle');
  const input = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (!toggle || !input) return;

  const eyeOpen = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
  const eyeClosed = '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.77 18.77 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';

  toggle.addEventListener('click', function(){
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    icon.innerHTML = isPassword ? eyeClosed : eyeOpen;
    toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
  });
})();
</script>
@endslot

</x-guest-layout>
