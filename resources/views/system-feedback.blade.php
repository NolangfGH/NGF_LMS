@extends('layouts.teacher-learning')

@section('content')
<p class="text-xs font-bold uppercase tracking-widest text-secondary">System administration</p>
<h1 class="mt-2 text-3xl font-extrabold text-primary">System feedback inbox</h1>
<p class="mt-2 text-sm leading-6">Review private LMS experiences, suggestions and problems submitted by signed-in users.</p>
<form method="GET" action="{{ route('school.feedback') }}" class="my-6 flex flex-wrap items-end gap-3">
    <div>
        <label for="inbox-category" class="block text-sm font-bold text-primary">Filter by type</label>
        <select name="category" id="inbox-category" class="mt-2 rounded-xl border border-green-200 bg-white p-3 text-sm">
            <option value="">All feedback</option>
            @foreach($categories as $key => $label)<option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <button class="rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Apply filter</button>
</form>
@include('system-feedback-entries', ['showAuthor' => true])
@endsection
