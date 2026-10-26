{{-- resources/views/partials/audit-badge.blade.php
     Usage: @include('partials.audit-badge', ['kind' => 'severity'|'finding'|'fine', 'value' => '...']) --}}
@php
    $classes = [
        'severity' => ['high' => 'rejected', 'medium' => 'pending', 'low' => 'waived'],
        'finding'  => ['open' => 'pending', 'acknowledged' => 'waived', 'resolved' => 'paid', 'dismissed' => 'absent'],
        'fine'     => ['unpaid' => 'unpaid', 'paid' => 'paid', 'waived' => 'waived'],
    ];
@endphp
<span class="badge {{ $classes[$kind][$value] ?? '' }}">{{ ucfirst($value) }}</span>
