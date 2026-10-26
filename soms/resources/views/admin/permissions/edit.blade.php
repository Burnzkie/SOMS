<x-layout title="Edit Permissions">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')
<div class="topbar">
    <div>
        <h1>{{ $position->user->name }}</h1>
        <p class="meta">{{ $position->position_title }} &middot; {{ $position->user->student_id }}</p>
    </div>
    <a href="{{ route('admin.permissions.index') }}" class="mini-btn">&larr; Back to Permissions</a>
</div>

<div class="panel">
    <div class="panel-head"><h3>Access</h3></div>

    <form method="POST" action="{{ route('admin.permissions.update', $position) }}" style="padding:16px;">
        @csrf
        @method('PUT')

        <div style="display:flex; flex-direction:column; gap:10px;">
            @foreach($availablePermissions as $key => $label)
            <label style="display:flex; align-items:center; gap:8px; font-size:13px;">
                <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $position->permissions ?? []))>
                {{ $label }}
            </label>
            @endforeach
        </div>

        <div style="display:flex; align-items:center; gap:10px; margin-top:16px; flex-wrap:wrap;">
            <button type="submit" class="mini-btn approve">Save permissions</button>
            {{-- Ticks the boxes recommended for this position. Nothing is saved until Save is pressed. --}}
            <button type="button" class="mini-btn" id="apply-recommended"
                    data-recommended='@json($recommended)'>Apply recommended for {{ $position->position_title }}</button>
        </div>
    </form>
</div>

<script>
// "Apply recommended": tick exactly the boxes recommended for this position.
// Only changes the form; the Admin still reviews and presses Save.
document.getElementById('apply-recommended').addEventListener('click', function () {
  var recommended = JSON.parse(this.getAttribute('data-recommended'));
  document.querySelectorAll('input[name="permissions[]"]').forEach(function (box) {
    box.checked = recommended.indexOf(box.value) !== -1;
  });
});
</script>
@endslot

</x-layout>
