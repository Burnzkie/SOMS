<x-layout title="Analytics">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Analytics</h1>
    <p class="meta">Aggregate usage only — no personal data is collected or stored. See the note at the bottom of this page.</p>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon violet">📈</div>
    <div class="value">{{ number_format($totalToday) }}</div>
    <div class="label">Hits today</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">📅</div>
    <div class="value">{{ number_format($totalLast7) }}</div>
    <div class="label">Last 7 days</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber">🗓️</div>
    <div class="value">{{ number_format($totalLast30) }}</div>
    <div class="label">Last 30 days</div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Daily hits — last 30 days</h3></div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Web</th>
          <th>Mobile</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        @forelse($dailyBreakdown as $date => $counts)
        <tr>
          <td>{{ \Illuminate\Support\Carbon::parse($date)->format('M j, Y') }}</td>
          <td>{{ number_format($counts['web']) }}</td>
          <td>{{ number_format($counts['mobile']) }}</td>
          <td><b>{{ number_format($counts['web'] + $counts['mobile']) }}</b></td>
        </tr>
        @empty
        <tr><td colspan="4"><p class="empty-note">No data yet — this fills in as the app is used.</p></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h3>Most-visited pages — last 30 days</h3></div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Route</th>
          <th>Hits</th>
        </tr>
      </thead>
      <tbody>
        @forelse($topRoutes as $route)
        <tr>
          <td><code>{{ $route->route_name }}</code></td>
          <td>{{ number_format($route->hits) }}</td>
        </tr>
        @empty
        <tr><td colspan="2"><p class="empty-note">No data yet.</p></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<p class="meta" style="margin-top: 16px;">
  Every row above is an aggregate count only — a date, a page name, and a
  number. No student is ever identified, no IP address or device
  information is recorded, and nothing here leaves this server. This was
  built as a first-party replacement for a third-party analytics service
  specifically so usage data never has to be shared outside the school's
  own database (RA 10173 / Data Privacy Act of 2012).
</p>
@endslot

</x-layout>
