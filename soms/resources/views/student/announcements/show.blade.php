<x-layout :title="$announcement->title">

@slot('nav')
@include('partials.student-nav')
@endslot

@slot('content')
<div class="topbar">
  <div>
    <h1>{{ $announcement->title }}</h1>
    <p class="meta">{{ $announcement->created_at->format('l, F j, Y \a\t g:i A') }}</p>
  </div>
  <a href="{{ route('student.announcements.index') }}" class="link-sm">← All announcements</a>
</div>

<div class="panel">
  <p style="font-size:14px; line-height:1.7; white-space:pre-line;">{{ $announcement->body }}</p>
</div>
@endslot

</x-layout>
