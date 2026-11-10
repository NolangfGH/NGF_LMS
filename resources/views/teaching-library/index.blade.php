@extends('layouts.teacher-learning')
@section('content')
@php($arduinoCourse = $course === 'arduino-uno')
@include('course-navigation', ['courseNavigationRoute' => 'teacher.library', 'selectedCourse' => $course, 'courseNavigationLabel' => 'Library course'])
<div class="flex flex-wrap items-start justify-between gap-5">
    <div class="max-w-2xl">
        <p class="text-xs font-bold uppercase tracking-widest text-secondary">Plan · personalize · teach</p>
        <h1 class="mt-3 text-3xl font-extrabold text-primary">Ready-made teaching library</h1>
        <p class="mt-3 text-sm leading-7">{{ $arduinoCourse ? 'Explore 25 Grade 9 lessons from robotics foundations to a tested classroom capstone.' : 'Follow the C++ topic path from Introduction to Function Parameters.' }} Every topic has a 10-question quiz, assignment, challenge, article and five editable recitation questions. Review a pack, create your editable draft, then publish to your classes.</p>
    </div>
    <a href="{{ route('teacher.lessons') }}" class="rounded-xl border border-green-200 bg-white px-5 py-3 text-sm font-bold text-primary">Open Lesson Studio →</a>
</div>
<div class="my-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    @foreach([[$topicCount, $arduinoCourse ? 'Arduino Uno Robotics lessons' : 'C++ lesson topics'], [$topicCount * 10, 'Multiple-choice questions'], [$topicCount * 2, 'Coding assignments & challenges'], [$topicCount, 'Editable blog articles'], [$topicCount * 5, 'Ready-made recitation questions']] as [$number, $label])
        <div class="rounded-2xl border border-green-100 bg-white p-5 shadow-card"><b class="text-3xl text-primary">{{ $number }}</b><p class="mt-2 text-sm">{{ $label }}</p></div>
    @endforeach
</div>
<div class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-5 text-sm leading-7"><b class="text-primary">You stay in control.</b> Copying creates a private draft. Edit the content and settings in the existing builder. Quizzes and coding tasks target selected sections; blog articles use your blog audience across all your sections. Publish only after your review.</div>
@if($arduinoCourse)<div class="mb-6 flex flex-wrap gap-3"><a class="rounded-xl border border-green-200 bg-white px-4 py-3 text-sm font-bold text-primary" href="{{ route('teacher.post-test.index', ['course'=>'arduino-uno']) }}">Arduino certification requests &amp; codes →</a><a class="rounded-xl border border-green-200 bg-white px-4 py-3 text-sm font-bold text-primary" href="{{ route('teacher.lessons.progress', ['course'=>'arduino-uno']) }}">Arduino learning progress →</a></div>@endif
<details id="term-exams" class="mb-6 rounded-2xl border border-green-200 bg-white p-5" @if(request('term')) open @endif>
    <summary class="cursor-pointer text-lg font-extrabold text-primary">Summative &amp; term exams</summary>
    <p class="my-3 text-sm leading-6">Two summative exams and one term exam for each term. Each has 25 multiple-choice questions, automatic checking and the usual quiz XP. Review a set, copy it to a private draft, and edit it in Quiz Builder.</p>
    <div class="grid gap-3 md:grid-cols-3">
        @foreach($termExams as $exam)
            <a href="{{ route('teacher.library.show', [$exam['key'], 'quiz']) }}" class="rounded-xl border border-green-200 bg-paper px-4 py-3 text-sm font-bold text-primary hover:bg-green-100">{{ $exam['title'] }} →</a>
        @endforeach
    </div>
</details>
<nav aria-label="Teaching library school term" class="mb-5 flex flex-wrap gap-2">
    @foreach(['' => 'All topics', 'Term1' => 'Term 1 · Foundations', 'Term2' => $arduinoCourse ? 'Term 2 · Sensors & actuators' : 'Term 2 · Decisions & collections', 'Term3' => $arduinoCourse ? 'Term 3 · Integration & robotics' : 'Term 3 · Data & functions'] as $term => $label)
        <a href="{{ route('teacher.library', array_filter(['term' => $term, 'q' => request('q'), 'course' => $course])) }}" @if((string)request('term') === (string)$term) aria-current="page" @endif class="rounded-xl border border-green-200 px-4 py-3 text-xs font-bold {{ (string)request('term') === (string)$term ? 'bg-primary text-white' : 'bg-white text-primary' }}">{{ $label }}</a>
    @endforeach
</nav>
<form method="GET" action="{{ route('teacher.library') }}" class="mb-6 flex flex-wrap gap-3" role="search">
    @if(request('term'))<input type="hidden" name="term" value="{{ request('term') }}">@endif
    <input type="hidden" name="course" value="{{ $course }}"><label class="sr-only" for="librarySearch">Find a topic</label>
    <input id="librarySearch" type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Find a topic, e.g. loops or arrays" class="min-w-0 flex-1 rounded-xl border border-green-200 bg-white px-4 py-3">
    <button class="rounded-xl bg-primary px-5 py-3 font-bold text-white">Find topic</button>
    @if(request()->filled('q'))<a href="{{ route('teacher.library', ['course'=>$course]) }}" class="rounded-xl border bg-white px-4 py-3">Clear</a>@endif
</form>
<div class="grid gap-5 xl:grid-cols-2">
@forelse($catalog as $key => $lesson)
    <article class="flex flex-col rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <div class="flex items-center justify-between gap-3"><span class="rounded-full bg-paper px-3 py-1 text-xs font-bold text-secondary">{{ $lesson['term'] }} · {{ $arduinoCourse ? 'Arduino Uno Robotics' : 'C++ learning path' }}</span><span class="font-mono text-xs text-secondary">10 quiz questions · 5 activity packs</span></div>
        <h2 class="mt-4 text-xl font-extrabold text-primary">{{ $lesson['topic'] }}</h2>
        <p class="mt-1 text-xs font-bold text-secondary">{{ $lesson['title'] }}</p>
        <p class="my-4 text-sm leading-6">{{ $lesson['objective'] }}</p>
        <details class="mb-5 rounded-xl bg-paper p-3 text-xs leading-6"><summary class="cursor-pointer font-bold text-primary">{{ count($lesson['subtopics']) }} subtopics in this lesson</summary><ul class="mt-2 list-inside list-disc">@foreach($lesson['subtopics'] as $subtopic)<li>{{ $subtopic }}</li>@endforeach</ul></details>
        <div class="mt-auto flex flex-wrap gap-2">
            <a href="{{ route('teacher.ilaw.edit', $key) }}" class="rounded-xl border border-green-200 bg-paper px-3 py-3 text-xs font-bold text-primary hover:bg-green-100">Edit ILAW lesson plan →</a>
            @foreach(['quiz' => 'Review 10-question quiz', 'assignment' => 'Review assignment', 'challenge' => 'Review challenge', 'article' => 'Review blog/article', 'recitation' => 'Review 5 recitation questions'] as $kind => $label)
                <a href="{{ route('teacher.library.show', [$key, $kind]) }}" class="rounded-xl border border-green-200 px-3 py-3 text-xs font-bold text-primary hover:bg-paper">{{ $label }} →</a>
            @endforeach
        </div>
    </article>
@empty
    <p class="rounded-2xl bg-white p-6">No topics match your search. Try a different topic or clear the search.</p>
@endforelse
</div>
<p class="mt-7 text-xs leading-6 text-secondary">{{ $arduinoCourse ? 'Original Grade 9 robotics lessons and editable activity packs. Check exact kit parts, wiring and library versions before classroom use. Reference:' : 'Original NGF classroom explanations and activities, organized using the' }} <a href="{{ $arduinoCourse ? config('arduino_curriculum.reference_url') : config('cpp_curriculum.reference_url') }}" target="_blank" rel="noopener noreferrer" class="font-bold underline">{{ $arduinoCourse ? 'Arduino Uno R3 documentation' : 'W3Schools C++ topic outline' }}</a> as a reference. Teachers can adapt suggested terms and difficulty to their grade and section.</p>
@endsection
