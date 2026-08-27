<x-layout title="My QR Code">

@slot('nav')
@include('partials.student-nav')
@endslot

@slot('styles')
<style>
.qr-card{
  max-width:380px;
  margin:0 auto;
  text-align:center;
  padding:32px;
}
.qr-card .qr-frame{
  background:#fff;
  border-radius:var(--radius-md);
  padding:20px;
  display:inline-block;
  margin-bottom:20px;
  position:relative;
}
.qr-card .qr-frame.refreshing{ opacity:.55; transition:opacity .15s var(--ease); }
.qr-card p.hint{
  font-size:12.5px;
  color:var(--text-muted);
  margin-bottom:16px;
  line-height:1.5;
}
.qr-countdown{
  display:inline-flex; align-items:center; gap:6px;
  font-size:11.5px; font-weight:700; color:var(--text-muted);
  background:var(--surface-2); border-radius:999px; padding:5px 12px;
}
.qr-countdown .dot{width:6px; height:6px; border-radius:50%; background:var(--emerald);}
.qr-countdown.stale .dot{background:var(--rose);}
</style>
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>My QR Code</h1>
    <p class="meta">Present this at scan stations for event attendance.</p>
  </div>
</div>

<div class="panel qr-card">
  <div class="qr-frame" id="qr-frame">
    {!! $qrSvg !!}
  </div>
  <div>
    <span class="qr-countdown" id="qr-countdown"><span class="dot"></span> <span id="qr-countdown-text">refreshes in {{ $expiresIn }}s</span></span>
  </div>
  <p class="hint" style="margin-top:16px;">This QR code changes automatically about a minute. Just keep this page open when you're about to scan in.</p>
</div>

<script>
  (function () {
    var frame = document.getElementById('qr-frame');
    var countdownText = document.getElementById('qr-countdown-text');
    var countdownEl = document.getElementById('qr-countdown');
    var secondsLeft = {{ (int) $expiresIn }};
    var pollUrl = '{{ route('student.qr.current') }}';

    function tickCountdown() {
      secondsLeft = Math.max(0, secondsLeft - 1);
      countdownText.textContent = secondsLeft > 0
        ? 'refreshes in ' + secondsLeft + 's'
        : 'refreshing…';
      countdownEl.classList.toggle('stale', secondsLeft <= 5);
    }

    function refreshQr() {
      fetch(pollUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (res) {
          if (!res.ok) { throw new Error('refresh failed'); }
          return res.json();
        })
        .then(function (json) {
          if (!json.success) { return; }
          frame.classList.add('refreshing');
          frame.innerHTML = json.data.svg;
          secondsLeft = json.data.expires_in;
          countdownEl.classList.remove('stale');
          setTimeout(function () { frame.classList.remove('refreshing'); }, 150);
        })
        .catch(function () {
          // Silent retry on next interval — a missed poll just means the
          // visible code goes a little stale, it doesn't break anything.
        });
    }

    // Tick the visible countdown every second, but only actually fetch a
    // fresh token every 15s — comfortably inside the ~60s rotation window
    // while keeping the polling load light (relevant on the free-tier
    // connection cap, see 03-Auth-Security.md §20.9).
    setInterval(tickCountdown, 1000);
    setInterval(refreshQr, 15000);

    // Also refresh immediately if the student returns to this tab after
    // being away (e.g. switched apps while walking to the scan station).
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) { refreshQr(); }
    });
  })();
</script>
@endslot

</x-layout>
