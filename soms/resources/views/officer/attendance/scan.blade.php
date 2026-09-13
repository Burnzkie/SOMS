<x-layout title="Scan Station">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('styles')
<style>
.scan-input{ position:absolute; opacity:0; pointer-events:none; }
.scan-feed{ max-height:420px; overflow-y:auto; }
.scan-row{ display:flex; align-items:center; gap:12px; padding:12px; border-radius:var(--radius-md); background:var(--surface-2); margin-bottom:8px; }
.scan-row .status-dot{ width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.scan-row.present .status-dot{ background:var(--emerald); }
.scan-row.rejected .status-dot{ background:var(--rose); }
.scan-row.already_marked .status-dot{ background:var(--amber); }
</style>
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>{{ ucfirst($session->session_type) }} scan station</h1>
    <p class="meta">{{ $session->eventDay->event->title }} &middot; {{ $session->eventDay->date->format('M j, Y') }}
      &middot; window {{ $session->timein_start->format('g:i A') }} – {{ $session->timein_end->format('g:i A') }}</p>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Ready to scan</h3><span class="link-sm" id="manual-toggle" role="button" tabindex="0" aria-expanded="false" aria-controls="manual-form">Manual override</span></div>
  <p class="empty-note">Point the USB/Bluetooth HID scanner at a student's QR code — this page stays focused and listens automatically. Click anywhere on the page if scanning stops responding.</p>
  <input type="text" id="qr-input" class="scan-input" autofocus>

  <div id="manual-form" style="display:none; margin-top:14px; padding:16px; border-radius:var(--radius-md); background:var(--surface-2);">
    <form id="override-form">
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <input type="text" name="student_id" placeholder="Student ID" class="field-input" style="flex:1; min-width:160px;" required>
        <select name="scan_type" class="field-input" style="flex:1; min-width:140px;">
          <option value="time_in">Time in</option>
          <option value="time_out">Time out</option>
        </select>
        <input type="password" name="password" placeholder="Your password (re-auth)" class="field-input" style="flex:1; min-width:180px;" required>
      </div>
      <input type="text" name="override_reason" placeholder="Reason for manual override" class="field-input" style="width:100%; margin-top:10px;" required>
      <button type="submit" class="btn btn-primary" id="override-submit" style="width:auto; padding:0 20px; margin-top:10px;">Record override</button>
      <div id="override-error" class="alert alert-error" style="display:none; margin-top:10px;" role="alert"></div>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Live feed</h3></div>
  {{-- Roadmap Phase 3.2 -- aria-live="polite" so each new scan result is
       announced to screen reader users as it arrives, not just visually
       appended. "polite" (not "assertive") so a burst of scans doesn't
       interrupt whatever the officer is currently doing. --}}
  <div class="scan-feed" id="scan-feed" aria-live="polite" role="log">
    <div class="empty-note">Scans will appear here.</div>
  </div>
</div>

<script>
(function () {
  var input = document.getElementById('qr-input');
  var feed = document.getElementById('scan-feed');
  var scanUrl = '{{ route('officer.attendance.scan') }}';
  var overrideUrl = '{{ route('officer.attendance.override', $session) }}';
  var sessionId = {{ $session->id }};
  var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
  var feedEmpty = true;

  document.addEventListener('click', function () { input.focus(); });
  input.focus();

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && input.value.trim()) {
      submitScan(input.value.trim());
      input.value = '';
      input.focus();
    }
  });

  function addFeedRow(status, label) {
    if (feedEmpty) { feed.innerHTML = ''; feedEmpty = false; }
    var row = document.createElement('div');
    row.className = 'scan-row ' + status;

    // Roadmap Phase 3.2 -- `label` includes the matched student's name,
    // sourced from the server. It was being inserted via innerHTML string
    // concatenation, which is a stored-XSS vector if a name ever contains
    // HTML-significant characters (registration doesn't strip/encode them
    // server-side, only validates length/type). Building nodes directly and
    // using textContent avoids that entirely -- no HTML parsing of `label`
    // happens at all now.
    var dot = document.createElement('span');
    dot.className = 'status-dot';
    dot.setAttribute('aria-hidden', 'true'); // decorative -- the text label carries the same info

    var labelEl = document.createElement('span');
    labelEl.textContent = label;

    var timeEl = document.createElement('span');
    timeEl.style.marginLeft = 'auto';
    timeEl.style.fontSize = '11px';
    timeEl.style.color = 'var(--text-faint)';
    timeEl.textContent = new Date().toLocaleTimeString();

    row.append(dot, labelEl, timeEl);
    feed.prepend(row);
  }

  function submitScan(token) {
    fetch(scanUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: JSON.stringify({ token: token, session_id: sessionId }),
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        var status = data.status || 'rejected';
        var label = status === 'present' ? (data.student_name + ' — present')
          : status === 'already_marked' ? (data.student_name + ' — already marked')
          : 'Rejected: ' + (data.reason || 'unknown');
        addFeedRow(status, label);
      })
      .catch(function () { addFeedRow('rejected', 'Network error — scan not recorded'); });
  }

  var manualToggle = document.getElementById('manual-toggle');

  function toggleManualForm() {
    var el = document.getElementById('manual-form');
    var isOpen = el.style.display !== 'none';
    el.style.display = isOpen ? 'none' : 'block';
    manualToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    if (!isOpen) {
      var firstField = el.querySelector('input, select');
      if (firstField) firstField.focus();
    }
  }

  manualToggle.addEventListener('click', toggleManualForm);

  // Roadmap Phase 3.2 -- this toggle is a <span role="button"> (kept as a
  // span rather than a real <button> to avoid disturbing the existing
  // .link-sm styling), so Enter/Space activation has to be wired up
  // manually -- native buttons get this for free, custom ARIA widgets don't.
  manualToggle.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      toggleManualForm();
    }
  });

  document.getElementById('override-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = e.target;
    var submitBtn = document.getElementById('override-submit');
    var errorBox = document.getElementById('override-error');
    var body = new URLSearchParams(new FormData(form));

    errorBox.style.display = 'none';
    submitBtn.disabled = true;

    fetch(overrideUrl, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: body,
    })
      .then(function (res) {
        if (res.redirected) { window.location.href = res.url; return; }
        // Roadmap Phase 1.3 — previously any non-network failure (wrong
        // re-auth password, validation error, 500) still hit the success
        // .then() below because res.ok was never checked. The officer saw
        // the form silently reset and had no way to know the override
        // hadn't actually been recorded.
        return res.json().then(function (data) {
          if (!res.ok) {
            var message = (data && data.message) ? data.message
              : (data && data.errors) ? Object.values(data.errors).flat().join(' ')
              : 'Override failed (' + res.status + ').';
            throw new Error(message);
          }
          return data;
        });
      })
      .then(function (result) {
        if (result === undefined) return; // redirect case, already navigating away
        form.reset();
        input.focus();
      })
      .catch(function (err) {
        errorBox.textContent = err.message || 'Network error — override not recorded.';
        errorBox.style.display = 'block';
      })
      .finally(function () {
        submitBtn.disabled = false;
      });
  });
})();
</script>
@endslot

</x-layout>
