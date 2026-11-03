@extends('layouts.teacher-learning')
@section('content')
<div class="flex flex-wrap items-start justify-between gap-5">
    <div class="max-w-2xl">
        <p class="text-xs font-bold uppercase tracking-widest text-secondary">Plan · personalize · teach</p>
        <h1 class="mt-3 text-3xl font-extrabold text-primary">Ready-made teaching library</h1>
        <p class="mt-3 text-sm leading-7">Start with a lesson-aligned quiz, coding task or blog article. Review it, make your own editable draft, and publish when ready.</p>
    </div>
    <a href="{{ route('teacher.lessons') }}" class="rounded-xl border border-green-200 bg-white px-5 py-3 text-sm font-bold text-primary">Open Lesson Studio →</a>
</div>
<div class="my-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([[$topicCount, 'C++ lesson topics'], [$topicCount * 10, 'Multiple-choice questions'], [$topicCount * 2, 'Coding assignments & challenges'], [$topicCount, 'Editable blog articles']] as [$number, $label])
        <div class="rounded-2xl border border-green-100 bg-white p-5 shadow-card"><b class="text-3xl text-primary">{{ $number }}</b><p class="mt-2 text-sm">{{ $label }}</p></div>
    @endforeach
</div>
<div class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-5 text-sm leading-7"><b class="text-primary">You stay in control.</b> Copying creates a private draft. Edit the content and settings in the existing builder. Quizzes and coding tasks target selected sections; blog articles use your blog audience across all your sections. Publish only after your review.</div>
<form method="GET" action="{{ route('teacher.library') }}" class="mb-6 flex flex-wrap gap-3" role="search">
    <label class="sr-only" for="librarySearch">Find a topic</label>
    <input id="librarySearch" type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Find a topic, e.g. loops or arrays" class="min-w-0 flex-1 rounded-xl border border-green-200 bg-white px-4 py-3">
    <button class="rounded-xl bg-primary px-5 py-3 font-bold text-white">Find topic</button>
    @if(request()->filled('q'))<a href="{{ route('teacher.library') }}" class="rounded-xl border bg-white px-4 py-3">Clear</a>@endif
</form>
<div class="grid gap-5 xl:grid-cols-2">
@forelse($catalog as $key => $lesson)
    <article class="flex flex-col rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <div class="flex items-center justify-between gap-3"><span class="rounded-full bg-paper px-3 py-1 text-xs font-bold text-secondary">{{ $lesson['term'] }} · Grade 8</span><span class="font-mono text-xs text-secondary">{{ str_pad((string)($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span></div>
        <h2 class="mt-4 text-xl font-extrabold text-primary">{{ $lesson['topic'] }}</h2>
        <p class="mt-1 text-xs font-bold text-secondary">{{ $lesson['title'] }}</p>
        <p class="my-4 text-sm leading-6">{{ $lesson['objective'] }}</p>
        <div class="mt-auto flex flex-wrap gap-2">
            @foreach(['quiz' => 'Review 10-question quiz', 'assignment' => 'Review assignment', 'challenge' => 'Review challenge', 'article' => 'Review blog/article'] as $kind => $label)
                <a href="{{ route('teacher.library.show', [$key, $kind]) }}" class="rounded-xl border border-green-200 px-3 py-3 text-xs font-bold text-primary hover:bg-paper">{{ $label }} →</a>
            @endforeach
        </div>
    </article>
@empty
    <p class="rounded-2xl bg-white p-6">No topics match your search. Try a different topic or clear the search.</p>
@endforelse
</div>
@endsection
