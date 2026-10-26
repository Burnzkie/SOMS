{{-- resources/views/admin/audit-findings/index.blade.php --}}
<x-layout title="Audit Findings">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Audit Findings</h1>
    <p class="meta">{{ $activeCount }} waiting for action &middot; reported by the Auditor</p>
  </div>
</div>

<div class="panel">
  <form method="GET" class="filter-form">
    <select name="status" class="field-input" onchange="this.form.submit()">
      <option value="">All statuses</option>
      @foreach(\App\Models\AuditFinding::STATUSES as $s)
        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
      @endforeach
    </select>
    <select name="severity" class="field-input" onchange="this.form.submit()">
      <option value="">All severities</option>
      @foreach(\App\Models\AuditFinding::SEVERITIES as $s)
        <option value="{{ $s }}" @selected(request('severity') === $s)>{{ ucfirst($s) }}</option>
      @endforeach
    </select>
  </form>

  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>#</th><th>Finding</th><th>Severity</th><th>Status</th><th>Reported</th></tr></thead>
      <tbody>
        @forelse($findings as $finding)
        <tr>
          <td>{{ $finding->id }}</td>
          <td><a href="{{ route('admin.audit-findings.show', $finding) }}" class="link-sm">{{ $finding->title }}</a></td>
          <td>@include('partials.audit-badge', ['kind' => 'severity', 'value' => $finding->severity])</td>
          <td>@include('partials.audit-badge', ['kind' => 'finding', 'value' => $finding->status])</td>
          <td style="font-size:12px;">{{ $finding->reported_at->format('M j, Y') }} &middot; {{ $finding->reporter->name }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="empty-note">No findings.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="pagination-wrap">{{ $findings->links() }}</div>
</div>
@endslot

</x-layout>
