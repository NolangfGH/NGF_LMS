@extends('layouts.student', ['pageTitle' => 'Course assessments', 'active' => 'assessments'])

@section('content')
@include('course-navigation', ['courseNavigationRoute' => 'student.assessments.index', 'selectedCourse' => $courseKey, 'courseNavigationLabel' => 'Assessment course'])
<div class="mb-6 rounded-3xl bg-primary p-6 text-white sm:p-8">
    <p class="text-xs font-bold uppercase tracking-widest text-green-100">{{ $courseTitle }} learning path</p>
    <h1 class="mt-2 text-3xl font-extrabold">Course assessments</h1>
    <p class="mt-3 max-w-2xl text-sm leading-6">Complete the Student Reasoning Challenge and this course's pre-test before starting lessons. Finish every core checkpoint, then pass the timed post-test to earn your certificate of excellence.</p>
</div>
<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
    <article class="rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <p class="text-xs font-bold uppercase text-secondary">Step 1 · Required for both courses</p>
        <h2 class="mt-2 text-xl font-extrabold text-primary">Student Reasoning Challenge</h2>
        <p class="my-3 text-sm leading-6">Answer 50 questions on patterns, logic, number sense, language and scientific evidence. Complete it once at any score; your saved result counts for both courses.</p>
        @if($reasoning)<p class="mb-3 rounded-xl bg-paper p-3 text-sm font-bold text-primary">✓ Completed · {{ $reasoning->score }} / {{ $reasoning->total_questions }}</p>@endif
        <a href="{{ route('student.assessments.show', ['reasoning'] + $courseQuery) }}" class="inline-block rounded-xl {{ $reasoning ? 'border border-green-200 text-primary' : 'bg-primary text-white' }} px-4 py-3 text-sm font-bold">{{ $reasoning ? 'View reasoning results' : 'Open reasoning challenge' }} →</a>
    </article>
    <article class="rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <p class="text-xs font-bold uppercase text-secondary">Step 2</p>
        <h2 class="mt-2 text-xl font-extrabold text-primary">{{ $courseTitle }} Pre-Test</h2>
        <p class="my-3 text-sm leading-6">The pre-test has 50 questions. Complete it and the Student Reasoning Challenge to unlock this course's lessons. No passing score is required for either starting assessment.</p>
        @if($pretest)<p class="mb-3 text-sm font-bold text-primary">Saved score {{ $pretest->score }} / {{ $pretest->total_questions }}</p>@endif
        <a href="{{ route('student.assessments.show', $courseKey === 'arduino-uno' ? 'arduino-pre-test' : 'pre-test') }}" class="inline-block rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">{{ $pretest ? 'View pre-test result' : 'Open pre-test' }} →</a>
    </article>
    <article class="rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <p class="text-xs font-bold uppercase text-secondary">Step 3</p>
        <h2 class="mt-2 text-xl font-extrabold text-primary">Core lesson checkpoints</h2>
        <p class="my-3 text-sm leading-6">{{ $courseProgress['completed'] }} of {{ $courseProgress['total'] }} required checkpoints complete.</p>
        <progress class="mb-4 h-3 w-full accent-green-800" value="{{ $courseProgress['completed'] }}" max="{{ $courseProgress['total'] }}" aria-label="Core course checkpoints complete"></progress>
        <a href="{{ route('student.learn', $courseQuery) }}" class="inline-block rounded-xl border border-green-200 px-4 py-3 text-sm font-bold text-primary">View lessons →</a>
    </article>
    <article class="rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <p class="text-xs font-bold uppercase text-secondary">Step 4</p>
        <h2 class="mt-2 text-xl font-extrabold text-primary">{{ $courseTitle }} Post-Test</h2>
        <p class="my-3 text-sm leading-6">After your final checkpoint, request a personal code from your teacher. Score at least 45 out of 50 to earn the certificate of excellence.</p>
        @if($reasoning && $pretest && $courseProgress['completed'] === $courseProgress['total'])
            <a href="{{ route('student.post-test.show', $courseQuery) }}" class="inline-block rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Open post-test →</a>
        @else
            <p class="rounded-xl bg-paper px-4 py-3 text-sm font-bold text-secondary">Complete the reasoning challenge, course pre-test and core checkpoints first.</p>
        @endif
        @if($certificate)
            <a href="{{ route('student.assessments.certificate', $courseQuery) }}" class="mt-3 block text-sm font-bold text-primary underline">Open issued certificate →</a>
        @endif
    </article>
</div>
@endsection
