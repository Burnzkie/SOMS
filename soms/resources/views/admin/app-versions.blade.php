<x-layout title="App Versions">

@slot('nav')
@include('partials.admin-nav')
@endslot

@slot('content')

<div class="topbar">
    <div>
        <h1>App Versions</h1>
        <p class="meta">Publish a new SOMS Android release for in-app auto-update</p>
    </div>
</div>

@if(session('status'))
<div class="panel" style="border-color: var(--accent, #7c5cff); margin-bottom: 1rem;">
    <p style="margin:0;">{{ session('status') }}</p>
</div>
@endif

<div class="panel" style="margin-bottom: 1.5rem;">
    <div class="panel-head">
        <h3>Publish new version</h3>
    </div>
    <form method="POST" action="{{ route('admin.app-versions.store') }}" class="filter-form" style="display:flex; flex-direction:column; gap: 1rem; max-width: 640px;">
        @csrf
        <input type="hidden" name="platform" value="android">

        <div>
            <label for="version_code">Version code (Android versionCode — must be higher than the last published one)</label>
            <input type="number" min="1" id="version_code" name="version_code" class="field-input" value="{{ old('version_code') }}" required>
            @error('version_code')<div class="meta" style="color:#f87171;">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="version_name">Version name (e.g. 1.1.0 — matches pubspec.yaml before the +)</label>
            <input type="text" id="version_name" name="version_name" class="field-input" value="{{ old('version_name') }}" required maxlength="20">
            @error('version_name')<div class="meta" style="color:#f87171;">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="apk_url">APK URL (upload the signed release APK to R2 first, then paste the public URL here)</label>
            <input type="url" id="apk_url" name="apk_url" class="field-input" value="{{ old('apk_url') }}" required maxlength="2048" placeholder="https://.../soms-1.1.0.apk">
            @error('apk_url')<div class="meta" style="color:#f87171;">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="changelog">Changelog (shown to users in the update dialog)</label>
            <textarea id="changelog" name="changelog" class="field-input" rows="3" maxlength="2000">{{ old('changelog') }}</textarea>
        </div>

        <div>
            <label for="min_supported_version_code">Minimum supported version code (optional — installs below this are blocked entirely)</label>
            <input type="number" min="1" id="min_supported_version_code" name="min_supported_version_code" class="field-input" value="{{ old('min_supported_version_code') }}">
        </div>

        <div style="display:flex; align-items:center; gap:.5rem;">
            <input type="checkbox" id="force_update" name="force_update" value="1" {{ old('force_update') ? 'checked' : '' }}>
            <label for="force_update" style="margin:0;">Force update (blocks the app until the user updates — use sparingly)</label>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Publish version</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-head">
        <h3>Published versions</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Code</th>
                    <th>Status</th>
                    <th>Force update</th>
                    <th>Published by</th>
                    <th>Published</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($versions as $v)
                <tr>
                    <td>{{ $v->version_name }}</td>
                    <td>{{ $v->version_code }}</td>
                    <td>
                        <span class="badge {{ $v->is_active ? 'approved' : 'rejected' }}">
                            {{ $v->is_active ? 'Active' : 'Deactivated' }}
                        </span>
                    </td>
                    <td>
                        @if($v->force_update)
                            <span class="badge flagged">Forced</span>
                        @else
                            <span class="meta">—</span>
                        @endif
                    </td>
                    <td>{{ $v->publisher?->name ?? '—' }}</td>
                    <td>{{ $v->created_at->format('M j, Y g:i A') }}</td>
                    <td>
                        @if($v->is_active)
                        <form method="POST" action="{{ route('admin.app-versions.deactivate', $v) }}" onsubmit="return confirm('Deactivate version {{ $v->version_name }}? The app will fall back to the next active version.');">
                            @csrf
                            <button type="submit" class="link-sm" style="background:none;border:none;cursor:pointer;color:#f87171;">Deactivate</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="meta">No versions published yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endslot
</x-layout>
