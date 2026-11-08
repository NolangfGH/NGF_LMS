@extends('layouts.student', ['pageTitle' => 'C++ Certification Post-Test', 'active' => 'assessments'])

@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('student.assessments.index') }}" class="text-sm font-bold text-primary">← Course assessments</a>
    <h1 class="mb-4 mt-4 text-2xl font-extrabold text-primary sm:text-3xl">C++ Certification Post-Test</h1>
    <p class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">New attempts: each incorrect or unanswered question deducts 5 XP after the exam is graded. Your balance cannot go below zero. Certification uses your correct score, which is separate from XP. No per-question correctness or answer keys are disclosed in XP history.</p>
    @if($state['current'])
        @include('quiz-current-question', ['examQuestion' => $state['current']])
        @include('quiz-question-timer')
    @else
        @if($state['result'])
            <section aria-label="Exam result" class="mb-5 rounded-2xl border border-green-100 bg-white p-5 shadow-card">
                <p class="text-sm font-bold text-secondary">Correct score</p>
                <p class="mt-1 text-3xl font-extrabold text-primary">{{ $state['result']->score }} / {{ \App\Services\CoursePostTest::QUESTIONS }}</p>
                <p class="mt-2 text-lg font-bold {{ $state['result']->score >= \App\Services\CoursePostTest::PASS_SCORE ? 'text-primary' : 'text-amber-800' }}">{{ $state['result']->score >= \App\Services\CoursePostTest::PASS_SCORE ? 'Passed' : 'Failed' }}</p>
                @if($state['result']->score >= \App\Services\CoursePostTest::PASS_SCORE)
                    <a href="{{ route('student.assessments.certificate') }}" class="mt-4 inline-block rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Open excellence certificate →</a>
                @endif
            </section>
        @endif
        @if(!$state['ready'])
            <section class="rounded-2xl border border-green-100 bg-white p-5">
                <h2 class="text-lg font-extrabold text-primary">Post-test locked</h2>
                <p class="mt-2 text-sm leading-6">Submit the pre-test and finish all {{ $courseProgress['total'] }} core checkpoints. You have completed {{ $courseProgress['completed'] }}.</p>
                <a href="{{ route('student.learn') }}" class="mt-4 inline-block rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Open learning path →</a>
            </section>
        @elseif($state['next_attempt'])
            <section class="rounded-2xl border border-green-100 bg-white p-5">
                <h2 class="text-lg font-extrabold text-primary">{{ $state['next_attempt'] === 1 ? 'First attempt' : 'Second and final attempt' }}</h2>
                <p class="mt-2 text-sm leading-6">50 questions · {{ $state['next_attempt'] === 1 ? 90 : 60 }} seconds each · 45 correct to pass.</p>
                <p class="mt-2 text-sm leading-6">The timer starts as soon as you enter your teacher’s personal code. Questions open one at a time, and the timer continues if you switch apps or refresh.</p>
                @if(session('post_test_notice'))<p role="status" class="mt-4 rounded-xl bg-paper p-3 text-sm font-bold text-primary">{{ session('post_test_notice') }}</p>@endif
                @if(!$state['requested'])
                    <form method="POST" action="{{ route('student.post-test.request') }}" class="mt-4">
                        @csrf
                        <button class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white sm:w-auto">Request a post-test code</button>
                    </form>
                @else
                    <p class="mt-4 rounded-xl bg-paper p-3 text-sm font-bold text-primary">{{ $state['code_ready'] ? 'Your teacher has generated your code. Ask them for it when you’re ready.' : 'Your request is with your teacher. Ask for a current code before starting.' }}</p>
                    <form method="POST" action="{{ route('student.post-test.start') }}" class="mt-4 max-w-sm space-y-3">
                        @csrf
                        <label for="exam-code" class="block text-sm font-bold text-primary">Post-test code</label>
                        <input id="exam-code" name="code" type="text" required maxlength="10" minlength="10" pattern="[A-Fa-f0-9]{10}" autocomplete="off" autocapitalize="characters" spellcheck="false" class="w-full rounded-xl border border-green-200 px-4 py-3 text-base uppercase">
                        @error('code')<p role="alert" class="text-sm font-bold text-red-700">{{ $message }}</p>@enderror
                        <button class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white sm:w-auto">Start timed exam</button>
                    </form>
                @endif
            </section>
        @endif
    @endif
</div>
@endsection
