{{-- resources/views/officer/audit/dashboard.blade.php --}}
<x-layout title="Audit Overview">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Audit Overview</h1>
    <p class="meta">Read-only view of fines, payments and attendance. Problems are reported as findings, never edited here.</p>
  </div>
  <div>
    <a href="{{ route('officer.audit.export.fines') }}" class="btn btn-ghost">Export fines (CSV)</a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon orange">🧾</div>
    <div class="value">₱{{ number_format($summary['assessed_total'], 2) }}</div>
    <div class="label">Fines assessed ({{ $summary['assessed_count'] }})</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">💲</div>
    <div class="value">₱{{ number_format($summary['collected_total'], 2) }}</div>
    <div class="label">Collections recorded ({{ $summary['collected_count'] }} paid)</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber">⏳</div>
    <div class="value">₱{{ number_format($summary['outstanding_total'], 2) }}</div>
    <div class="label">Outstanding ({{ $summary['outstanding_count'] }} unpaid)</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">🤝</div>
    <div class="value">₱{{ number_format($summary['waived_total'], 2) }}</div>
    <div class="label">Waived ({{ $summary['waived_count'] }})</div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Records requiring review</h3></div>
  <table class="data-table">
    <tbody>
      <tr>
        <td>High-severity discrepancies</td>
        <td>@include('partials.audit-badge', ['kind' => 'severity', 'value' => 'high']) {{ $discrepancyCounts['high'] ?? 0 }}</td>
      </tr>
      <tr>
        <td>Medium-severity discrepancies</td>
        <td>@include('partials.audit-badge', ['kind' => 'severity', 'value' => 'medium']) {{ $discrepancyCounts['medium'] ?? 0 }}</td>
      </tr>
      <tr>
        <td>Low-severity discrepancies</td>
        <td>@include('partials.audit-badge', ['kind' => 'severity', 'value' => 'low']) {{ $discrepancyCounts['low'] ?? 0 }}</td>
      </tr>
      <tr>
        <td>Flagged scans waiting for a decision</td>
        <td>{{ $summary['pending_flagged'] }}</td>
      </tr>
      <tr>
        <td>Findings still open or acknowledged</td>
        <td>{{ $summary['active_findings'] }}</td>
      </tr>
    </tbody>
  </table>
  <p class="empty-note" style="text-align:left;">Counts refresh every couple of minutes. <a href="{{ route('officer.audit.discrepancies') }}" class="link-sm">Open the live discrepancy report →</a></p>
</div>

<div class="panel">
  <div class="panel-head"><h3>Recent findings</h3></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Finding</th><th>Severity</th><th>Status</th><th>Reported</th></tr></thead>
      <tbody>
        @forelse($recentFindings as $finding)
        <tr>
          <td><a href="{{ route('officer.audit.findings.show', $finding) }}" class="link-sm">{{ $finding->title }}</a></td>
          <td>@include('partials.audit-badge', ['kind' => 'severity', 'value' => $finding->severity])</td>
          <td>@include('partials.audit-badge', ['kind' => 'finding', 'value' => $finding->status])</td>
          <td>{{ $finding->reported_at->format('M j, Y') }} &middot; {{ $finding->reporter->name }}</td>
        </tr>
        @empty
        <tr><td colspan="4" class="empty-note">No findings recorded yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endslot

</x-layout>
