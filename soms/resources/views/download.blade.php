<x-guest-layout title="Download SOMS">

@slot('content')
<style>
body { overflow-y: auto !important; align-items: flex-start !important; height: auto !important; }
.auth-card { margin: 40px auto !important; }
</style>

<h2>Get the SOMS app</h2>
<p class="sub">Philippine Advent College SGO — official Android app</p>

@if($version)
  <p style="font-size:13px; color:var(--text-muted); margin-bottom:18px;">
    Version {{ $version->version_name }}
    @if($version->changelog)
      <br><span style="font-size:12px;">{{ $version->changelog }}</span>
    @endif
  </p>

  <a href="{{ $version->apk_url }}" class="btn btn-primary" style="text-decoration:none; margin-bottom:22px;">
    Download APK ({{ $version->version_name }})
  </a>

  <div style="margin:22px 0; padding-top:22px; border-top:1px solid var(--border-strong);">
    <p style="font-size:12px; font-weight:600; color:var(--text-muted); margin-bottom:12px;">
      After downloading:
    </p>
    <ol style="margin:0; padding-left:18px; font-size:13px; color:var(--text); line-height:1.7;">
      <li>Open the downloaded file from your notifications or Downloads folder.</li>
      <li>If Android blocks the install, tap <strong>Settings</strong> in the prompt and allow
        <strong>"Install unknown apps"</strong> for your browser — this is a one-time step.</li>
      <li>Go back and tap <strong>Install</strong>.</li>
      <li>Open SOMS and sign in with your Student ID.</li>
    </ol>
  </div>

  @if($qrSvg)
  <div style="margin-top:22px; padding-top:22px; border-top:1px solid var(--border-strong); text-align:center;">
    <p style="font-size:12px; font-weight:600; color:var(--text-muted); margin-bottom:12px;">
      Scan to open this page on your phone
    </p>
    <div style="background:#fff; display:inline-block; padding:10px; border-radius:12px;">
      {!! $qrSvg !!}
    </div>
  </div>
  @endif

@else
  <div class="alert alert-error">
    No app version has been published yet. Check back soon, or contact your SGO officer.
  </div>
@endif

@endslot
</x-guest-layout>
