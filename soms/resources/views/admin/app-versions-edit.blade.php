{{-- resources/views/admin/app-versions-edit.blade.php --}}
<x-layout title="Edit App Version">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')

<div class="topbar">
    <div>
        <h1>Edit version {{ $version->version_name }}</h1>
        <p class="meta">Version code {{ $version->version_code }} cannot be changed.</p>
    </div>
</div>

<div class="panel">
    <form method="POST" action="{{ route('admin.app-versions.update', $version) }}" class="filter-form" style="display:flex; flex-direction:column; gap:1rem; max-width:640px;">
        @csrf
        @method('PUT')

        <div>
            <label for="version_code">Version code (locked)</label>
            <input type="number" id="version_code" class="field-input" value="{{ $version->version_code }}" disabled>
        </div>

        <div>
            <label for="version_name">Version name (matches pubspec.yaml before the +, e.g. 1.4.0)</label>
            <input type="text" id="version_name" name="version_name" class="field-input" value="{{ old('version_name', $version->version_name) }}" required maxlength="20">
            @error('version_name')<div class="meta" style="color:#f87171;">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="apk_url">APK URL (public https URL)</label>
            <input type="url" id="apk_url" name="apk_url" class="field-input" value="{{ old('apk_url', $version->apk_url) }}" required maxlength="2048">
            @error('apk_url')<div class="meta" style="color:#f87171;">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="changelog">Changelog</label>
            <textarea id="changelog" name="changelog" class="field-input" rows="3" maxlength="2000">{{ old('changelog', $version->changelog) }}</textarea>
            @error('changelog')<div class="meta" style="color:#f87171;">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="min_supported_version_code">Minimum supported version code (optional)</label>
            <input type="number" min="1" max="{{ $version->version_code }}" id="min_supported_version_code" name="min_supported_version_code" class="field-input" value="{{ old('min_supported_version_code', $version->min_supported_version_code) }}">
            @error('min_supported_version_code')<div class="meta" style="color:#f87171;">{{ $message }}</div>@enderror
        </div>

        <div style="display:flex; align-items:center; gap:.5rem;">
            <input type="checkbox" id="force_update" name="force_update" value="1" {{ old('force_update', $version->force_update) ? 'checked' : '' }}>
            <label for="force_update" style="margin:0;">Force update (blocks the app until the user updates)</label>
        </div>

        <div style="display:flex; gap:.75rem;">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('admin.app-versions.index') }}" class="link-sm" style="align-self:center;">Cancel</a>
        </div>
    </form>
</div>

@endslot
</x-layout>