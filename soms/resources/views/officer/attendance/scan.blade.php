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
  <div class="panel-head"><h3>Ready to scan</h3><span class="link-sm" id="manual-toggle">Manual override</span></div>
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
      <button type="submit" class="btn btn-primary" style="width:auto; padding:0 20px; margin-top:10px;">Record override</button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Live feed</h3></div>
  <div class="scan-feed" id="scan-feed">
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
    row.innerHTML = '<span class="status-dot"></span><span>' + label + '</span>' +
      '<span style="margin-left:auto; font-size:11px; color:var(--text-faint);">' + new Date().toLocaleTimeString() + '</span>';
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

  document.getElementById('manual-toggle').addEventListener('click', function () {
    var el = document.getElementById('manual-form');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  });

  document.getElementById('override-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = e.target;
    var body = new URLSearchParams(new FormData(form));
    fetch(overrideUrl, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: body,
    })
      .then(function (res) {
        if (res.redirected) { window.location.href = res.url; return; }
        return res.json();
      })
      .then(function () { form.reset(); input.focus(); })
      .catch(function () {});
  });
})();
</script>
@endslot

</x-layout>
