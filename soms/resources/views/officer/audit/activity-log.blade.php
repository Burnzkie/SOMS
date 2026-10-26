{{-- resources/views/officer/audit/activity-log.blade.php --}}
<x-layout title="Activity Log (Audit)">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Activity Log</h1>
    <p class="meta">Read-only audit trail &middot; hash-chain integrity: {{ $chainOk ? 'OK' : 'BROKEN' }} &middot; IP addresses are masked</p>
  </div>
</div>

@unless($chainOk)
<div class="banner danger">⚠ The activity log hash chain failed verification. Report this to the Admin immediately.</div>
@endunless

<div class="panel">
  <form method="GET" class="filter-form">
    <select name="action" class="field-input" onchange="this.form.submit()">
      <option value="">All actions</option>
      @foreach($actions as $action)
        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
      @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" class="field-input" title="From">
    <input type="date" name="to" value="{{ request('to') }}" class="field-input" title="To">
    <button class="btn btn-ghost" style="width:auto; padding:0 18px;">Filter</button>
  </form>

  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
      <tbody>
        @forelse($logs as $log)
        <tr>
          <td style="white-space:nowrap;">{{ $log->created_at->format('M j, Y g:i A') }}</td>
          <td>{{ $log->user->name ?? 'System' }}</td>
          <td>{{ $log->action }}</td>
          <td style="font-size:12px;">{{ $log->model_type ? class_basename($log->model_type) . ' #' . $log->model_id : '—' }}</td>
          <td style="font-size:11px; color:var(--text-faint); max-width:260px; overflow-wrap:anywhere;">{{ $log->payload ? \Illuminate\Support\Str::limit(json_encode($log->payload), 120) : '—' }}</td>
          {{-- Only the masked value is ever shown; the raw ip_address column is not exposed. --}}
          <td style="font-size:12px;">{{ $log->ip_address_display ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="6" class="empty-note">No activity logs match.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="pagination-wrap">{{ $logs->links() }}</div>
</div>
@endslot

</x-layout>
