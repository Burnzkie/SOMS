<x-layout title="New Announcement">

@slot('nav')
@include('partials.officer-nav')
@endslot

@slot('content')
<div class="topbar"><div><h1>New announcement draft</h1></div></div>

<div class="panel">
  <form method="POST" action="{{ route('officer.announcements.store') }}">
    @csrf
    <div class="field" style="margin-bottom:14px;">
      <label>Title</label>
      <input type="text" name="title" value="{{ old('title') }}" required class="field-input" style="width:100%;">
    </div>
    <div class="field" style="margin-bottom:20px;">
      <label>Body</label>
      <textarea name="body" required class="field-input" style="width:100%; min-height:160px;">{{ old('body') }}</textarea>
    </div>
    <button class="btn btn-primary" style="width:auto; padding:0 24px;">Save draft</button>
  </form>
</div>
@endslot

</x-layout>
