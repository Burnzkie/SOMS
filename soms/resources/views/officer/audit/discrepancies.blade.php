{{-- resources/views/officer/audit/discrepancies.blade.php --}}
<x-layout title="Discrepancy Report">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>Discrepancy Report</h1>
    <p class="meta">{{ $issues->count() }} found right now. Checked against the data as it is at this moment; nothing here is changed or corrected.</p>
  </div>
  <div><a href="{{ route('officer.audit.export.discrepancies') }}" class="btn btn-ghost">Export CSV</a></div>
</div>

<div class="panel">
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Severity</th><th>Check</th><th>Student / event</th><th>Details</th><th></th></tr></thead>
      <tbody>
        @forelse($issues as $issue)
        @php($key = $issue['check'] . '|' . $issue['reference_type'] . '|' . $issue['reference_id'])
        <tr>
          <td>@include('partials.audit-badge', ['kind' => 'severity', 'value' => $issue['severity']])</td>
          <td>{{ \App\Services\AuditService::CHECK_LABELS[$issue['check']] ?? $issue['check'] }}</td>
          <td style="font-size:12px;">{{ $issue['student'] }}<br><span style="color:var(--text-faint);">{{ $issue['event'] }}</span></td>
          <td style="font-size:12px;">{{ $issue['summary'] }}</td>
          <td>
            @if(isset($findingFor[$key]))
              <a href="{{ route('officer.audit.findings.show', $findingFor[$key]) }}" class="link-sm">Finding #{{ $findingFor[$key] }}</a>
            @elseif(\App\Support\OfficerPermission::can(auth()->user(), 'manage_audit_findings'))
              <form method="POST" action="{{ route('officer.audit.findings.from-check') }}">
                @csrf
                <input type="hidden" name="check" value="{{ $issue['check'] }}">
                <input type="hidden" name="reference_type" value="{{ $issue['reference_type'] }}">
                <input type="hidden" name="reference_id" value="{{ $issue['reference_id'] }}">
                <button class="mini-btn">Report as finding</button>
              </form>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="5" class="empty-note">No discrepancies found. Records are consistent.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endslot

</x-layout>
