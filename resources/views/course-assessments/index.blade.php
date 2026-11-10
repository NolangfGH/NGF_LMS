@extends('layouts.student', ['pageTitle' => 'Course assessments', 'active' => 'assessments'])

@section('content')
<nav class="mb-5 flex flex-wrap gap-3" aria-label="Assessment course"><a class="rounded-xl border bg-white px-4 py-3 font-bold" href="{{ route('student.assessments.index') }}">C++ Programming</a><a class="rounded-xl border bg-white px-4 py-3 font-bold" href="{{ route('student.assessments.index',['course'=>'arduino-uno']) }}">Grade 9 Arduino Uno</a></nav>
<div class="mb-6 rounded-3xl bg-primary p-6 text-white sm:p-8">
    <p class="text-xs font-bold uppercase tracking-widest text-green-100">{{ $courseTitle }} learning path</p>
    <h1 class="mt-2 text-3xl font-extrabold">Course assessments</h1>
    <p class="mt-3 max-w-2xl text-sm leading-6">Submit the pre-test, finish every core lesson checkpoint, and pass the timed post-test to earn your certificate of excellence.</p>
</div>
<div class="grid gap-5 xl:grid-cols-3">
    <article class="rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <p class="text-xs font-bold uppercase text-secondary">Step 1</p>
        <h2 class="mt-2 text-xl font-extrabold text-primary">{{ $courseTitle }} Pre-Test</h2>
        <p class="my-3 text-sm leading-6">The current pre-test has 50 questions. Submitting it unlocks Introduction and the lesson path at any score.</p>
        @if($pretest)<p class="mb-3 text-sm font-bold text-primary">Saved score {{ $pretest->score }} / {{ $pretest->total_questions }}</p>@endif
        <a href="{{ route('student.assessments.show', $courseKey === 'arduino-uno' ? 'arduino-pre-test' : 'pre-test') }}" class="inline-block rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">{{ $pretest ? 'View pre-test result' : 'Open pre-test' }} →</a>
    </article>
    <article class="rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <p class="text-xs font-bold uppercase text-secondary">Step 2</p>
        <h2 class="mt-2 text-xl font-extrabold text-primary">Core lesson checkpoints</h2>
        <p class="my-3 text-sm leading-6">{{ $courseProgress['completed'] }} of {{ $courseProgress['total'] }} required checkpoints complete.</p>
        <progress class="mb-4 h-3 w-full accent-green-800" value="{{ $courseProgress['completed'] }}" max="{{ $courseProgress['total'] }}" aria-label="Core course checkpoints complete"></progress>
        <a href="{{ route('student.learn', $courseQuery) }}" class="inline-block rounded-xl border border-green-200 px-4 py-3 text-sm font-bold text-primary">View lessons →</a>
    </article>
    <article class="rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <p class="text-xs font-bold uppercase text-secondary">Step 3</p>
        <h2 class="mt-2 text-xl font-extrabold text-primary">{{ $courseTitle }} Post-Test</h2>
        <p class="my-3 text-sm leading-6">After your final checkpoint, request a personal code from your teacher. Score at least 45 out of 50 to earn the certificate of excellence.</p>
        @if($pretest && $courseProgress['completed'] === $courseProgress['total'])
            <a href="{{ route('student.post-test.show', $courseQuery) }}" class="inline-block rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Open post-test →</a>
        @else
            <p class="rounded-xl bg-paper px-4 py-3 text-sm font-bold text-secondary">Locked until the pre-test and core checkpoints are complete.</p>
        @endif
        @if($certificate)
            <a href="{{ route('student.assessments.certificate', $courseQuery) }}" class="mt-3 block text-sm font-bold text-primary underline">Open issued certificate →</a>
        @endif
    </article>
</div>
<article class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-green-100 bg-white p-6 shadow-card">
    <div>
        <p class="text-xs font-bold uppercase text-secondary">Optional practice</p>
        <h2 class="mt-2 text-xl font-extrabold text-primary">Grade 8 Reasoning Challenge</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6">Try 50 IQ-style questions on patterns, logic, number sense, language and scientific evidence. Your private results show what to practise next.</p>
    </div>
    <a href="{{ route('student.assessments.show', 'reasoning') }}" class="rounded-xl border border-green-200 bg-paper px-4 py-3 text-sm font-bold text-primary">{{ $reasoning ? 'View reasoning results' : 'Open reasoning challenge' }} →</a>
</article>
@endsection
