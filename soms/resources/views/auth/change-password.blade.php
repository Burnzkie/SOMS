<x-guest-layout title="Change Password">

@slot('content')
<h2>Change your password</h2>
<p class="sub">
  @if(auth()->user()->must_change_password ?? false)
    You're using the default password. Please set a new one to continue.
  @else
    Update your account password below.
  @endif
</p>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
  <div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('change-password.update') }}" id="changePasswordForm">
  @csrf

  <div class="field">
    <label for="current_password">Current password</label>
    <div class="password-wrap">
      <input type="password" id="current_password" name="current_password" placeholder="••••••••" required>
      <button type="button" class="password-toggle" data-target="current_password" aria-label="Show password" tabindex="-1">
        <svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
      </button>
    </div>
    @error('current_password')<div class="field-error">{{ $message }}</div>@enderror
  </div>

  <div class="field">
    <label for="password">New password</label>
    <div class="password-wrap">
      <input type="password" id="password" name="password" placeholder="••••••••" minlength="8" required>
      <button type="button" class="password-toggle" data-target="password" aria-label="Show password" tabindex="-1">
        <svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
      </button>
    </div>

    <div class="strength-meter" id="strengthMeter">
      <div class="strength-bars">
        <span class="bar" data-bar="1"></span>
        <span class="bar" data-bar="2"></span>
        <span class="bar" data-bar="3"></span>
      </div>
      <div class="strength-row">
        <span class="strength-label" id="strengthLabel">Minimum 8 characters</span>
        <span class="strength-count" id="strengthCount">0 / 8</span>
      </div>
      <ul class="strength-hints" id="strengthHints">
        <li data-rule="length">At least 8 characters</li>
        <li data-rule="case">Upper &amp; lowercase letters</li>
        <li data-rule="number">At least one number</li>
      </ul>
    </div>

    @error('password')<div class="field-error">{{ $message }}</div>@enderror
  </div>

  <div class="field">
    <label for="password_confirmation">Confirm new password</label>
    <div class="password-wrap">
      <input type="password" id="password_confirmation" name="password_confirmation" placeholder="••••••••" minlength="8" required>
      <button type="button" class="password-toggle" data-target="password_confirmation" aria-label="Show password" tabindex="-1">
        <svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
      </button>
    </div>
    <div class="field-error" id="matchError" style="display:none;">Passwords do not match.</div>
    @error('password_confirmation')<div class="field-error">{{ $message }}</div>@enderror
  </div>

  <button type="submit" class="btn btn-primary" id="submitBtn">Update password</button>
</form>

@unless(auth()->user()->must_change_password ?? false)
<p class="auth-foot"><a href="{{ url()->previous() }}">Cancel and go back</a></p>
@endunless

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

.strength-meter{ margin-top:10px; }
.strength-bars{ display:flex; gap:5px; margin-bottom:7px; }
.strength-bars .bar{
  flex:1; height:4px; border-radius:99px; background:rgba(255,255,255,.1);
  transition:background .2s var(--ease);
}
.strength-bars.weak .bar[data-bar="1"]{ background:var(--rose); }
.strength-bars.medium .bar[data-bar="1"],
.strength-bars.medium .bar[data-bar="2"]{ background:#F5A623; }
.strength-bars.strong .bar{ background:var(--emerald); }

.strength-row{ display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.strength-label{ font-size:11.5px; font-weight:600; color:var(--text-faint); }
.strength-label.weak{ color:var(--rose); }
.strength-label.medium{ color:#F5A623; }
.strength-label.strong{ color:var(--emerald); }
.strength-count{ font-size:11px; color:var(--text-faint); font-variant-numeric:tabular-nums; }
.strength-count.ok{ color:var(--emerald); }

.strength-hints{ list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:3px; }
.strength-hints li{
  font-size:11px; color:var(--text-faint); padding-left:18px; position:relative;
}
.strength-hints li::before{
  content:"○"; position:absolute; left:0; top:0; font-size:10px; color:var(--text-faint);
}
.strength-hints li.met{ color:var(--emerald); }
.strength-hints li.met::before{ content:"●"; color:var(--emerald); }
</style>

<script>
(function(){
  document.querySelectorAll('.password-toggle').forEach(function(toggle){
    toggle.addEventListener('click', function(){
      const input = document.getElementById(toggle.dataset.target);
      const icon = toggle.querySelector('.eye-icon');
      if (!input) return;
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      icon.innerHTML = isPassword
        ? '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.77 18.77 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>'
        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
      toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });
  });

  const passwordInput = document.getElementById('password');
  const confirmInput = document.getElementById('password_confirmation');
  const barsEl = document.querySelector('.strength-bars');
  const labelEl = document.getElementById('strengthLabel');
  const countEl = document.getElementById('strengthCount');
  const hintLength = document.querySelector('[data-rule="length"]');
  const hintCase = document.querySelector('[data-rule="case"]');
  const hintNumber = document.querySelector('[data-rule="number"]');
  const matchError = document.getElementById('matchError');
  const submitBtn = document.getElementById('submitBtn');

  function evaluate(value){
    const hasLength = value.length >= 8;
    const hasCase = /[a-z]/.test(value) && /[A-Z]/.test(value);
    const hasNumber = /\d/.test(value);

    hintLength.classList.toggle('met', hasLength);
    hintCase.classList.toggle('met', hasCase);
    hintNumber.classList.toggle('met', hasNumber);

    countEl.textContent = Math.min(value.length, 99) + ' / 8';
    countEl.classList.toggle('ok', hasLength);

    const metCount = [hasLength, hasCase, hasNumber].filter(Boolean).length;
    barsEl.classList.remove('weak', 'medium', 'strong');

    if (value.length === 0) {
      labelEl.textContent = 'Minimum 8 characters';
      labelEl.className = 'strength-label';
      return;
    }

    if (!hasLength || metCount <= 1) {
      barsEl.classList.add('weak');
      labelEl.textContent = 'Weak';
      labelEl.className = 'strength-label weak';
    } else if (metCount === 2) {
      barsEl.classList.add('medium');
      labelEl.textContent = 'Medium';
      labelEl.className = 'strength-label medium';
    } else {
      barsEl.classList.add('strong');
      labelEl.textContent = 'Strong';
      labelEl.className = 'strength-label strong';
    }
  }

  function checkMatch(){
    if (confirmInput.value.length === 0) {
      matchError.style.display = 'none';
      return;
    }
    const mismatch = passwordInput.value !== confirmInput.value;
    matchError.style.display = mismatch ? 'block' : 'none';
  }

  if (passwordInput) {
    passwordInput.addEventListener('input', function(){
      evaluate(passwordInput.value);
      checkMatch();
    });
  }
  if (confirmInput) {
    confirmInput.addEventListener('input', checkMatch);
  }
})();
</script>
@endslot

</x-guest-layout>
