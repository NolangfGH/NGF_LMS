@extends('layouts.student', ['pageTitle' => 'Quiz', 'active' => 'quiz'])
@section('content')
@if(session('excuse_notice'))<p role="status" class="mb-5 rounded-xl bg-green-100 p-4">{{ session('excuse_notice') }}</p>@endif
<div class="mb-7"><p class="text-xs font-bold uppercase tracking-widest text-secondary">{{ $classroom->grade }} · {{ $classroom->section ?: $classroom->name }}</p><h1 class="mt-2 text-3xl font-extrabold text-primary">My Quizzes</h1><p class="mt-2 text-sm text-muted">Check the schedule before you start. One regular timed attempt is allowed. An approved excuse can grant one final opportunity.</p></div>
<p class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">New quiz, summative and term-exam attempts: each incorrect or unanswered graded question deducts 5 XP after grading. Ungraded questions are excluded. Your balance cannot go below zero. See Achievements for your complete XP history.</p><div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Quiz XP rewards">
<div class="rounded-2xl border border-green-200 bg-white p-5"><p class="text-xs font-bold uppercase text-secondary">Your total XP</p><p class="mt-2 text-3xl font-extrabold text-primary">{{ number_format($student->xp) }} XP</p><a href="{{ route('student.leaderboard') }}" class="mt-2 inline-block text-xs font-bold underline">View classroom ranking →</a></div>
<div class="rounded-2xl border border-green-200 bg-green-50 p-5"><p class="text-xs font-bold uppercase text-secondary">Every correct answer</p><p class="mt-2 text-3xl font-extrabold text-primary">+10 XP</p><p class="mt-2 text-xs">Credited after the quiz is graded.</p></div>
<div class="rounded-2xl border border-amber-200 bg-amber-50 p-5"><p class="text-xs font-bold uppercase text-amber-800">Perfect quiz bonus</p><p class="mt-2 text-3xl font-extrabold text-amber-900">+100 XP</p><p class="mt-2 text-xs">Answer every question correctly to earn the bonus.</p></div>
<div class="rounded-2xl border border-sky-200 bg-sky-50 p-5"><p class="text-xs font-bold uppercase text-sky-800">Fast correct answer</p><p class="mt-2 text-3xl font-extrabold text-sky-900">+5 XP</p><p class="mt-2 text-xs">Submit the correct answer within 30 seconds.</p></div>
</div>
@if(session('quiz_saved'))<p role="status" class="mb-5 rounded-xl bg-green-100 p-4">{{ session('quiz_saved') }}</p>@endif
@if($errors->any())<p role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-red-800">{{ $errors->first() }}</p>@endif
@forelse($quizzes as $quiz)
@php
    $savedResult = $quizResults->get($quiz->id);
    $attempt = $quizAttempts->get($quiz->id);
    $open = $quiz->isOpen();
    $quizGrant=$secondChanceRequests->get('quiz:'.$quiz->id);
    if ($quizGrant?->status === 'approved') {
        $savedResult=$quizFinalResults->get($quiz->id);
        if ($attempt?->attempt_number !== 2) $attempt=null;
        $open=$quizGrant->isActive();
    }
@endphp
<section id="quiz-{{ $quiz->id }}" class="scroll-mt-24 mb-6 rounded-2xl border border-green-100 bg-white shadow-card">
<div class="border-b border-green-100 p-3 sm:p-6"><div class="flex flex-wrap items-center justify-between gap-3"><span class="rounded-full bg-green-50 px-3 py-1 text-xs font-bold text-primary">{{ $savedResult ? 'Completed' : ($open ? ($attempt ? 'In progress' : 'Ready to start') : ($quiz->opens_at && now()->lt($quiz->opens_at) ? 'Scheduled' : 'Closed')) }}</span><span class="text-xs font-bold text-secondary">{{ count($attempt?->questions ?? $quiz->questions) }} questions · {{ $attempt && !$attempt->sequential ? $quiz->time_limit.' minute limit' : ($attempt?->untimed ?? $quiz->untimed ? 'Untimed assessment' : ($attempt?->question_seconds ?? $quiz->question_seconds ?? 60).' seconds per question') }} · {{ $quizGrant?->status === 'approved' ? 'Final opportunity' : 'One attempt' }}</span></div><h2 class="mt-4 text-xl font-extrabold text-primary">{{ $quiz->title }}</h2><p class="mt-2 text-sm leading-6 text-muted">{{ $quiz->instructions }}</p>
<div class="mt-4 grid gap-3 rounded-xl bg-paper p-4 text-sm sm:grid-cols-2"><div><b>Opens</b><p>{{ ($quizGrant?->status === 'approved' ? $quizGrant->reviewed_at : $quiz->opens_at)?->timezone(config('learning.timezone'))->format('M j, Y · g:i A') ?? 'Anytime' }}</p></div><div><b>Closes</b><p>{{ ($quizGrant?->status === 'approved' ? $quizGrant->expires_at : $quiz->closes_at)?->timezone(config('learning.timezone'))->format('M j, Y · g:i A') ?? 'No closing time' }}</p></div><p class="text-xs text-secondary sm:col-span-2">Asia/Singapore · UTC+08:00</p></div></div>
<div class="p-3 sm:p-6">@if($quizGrant?->status === 'approved')<p class="mb-4 rounded-xl bg-amber-50 p-4 text-sm font-bold">Second and final opportunity. Original results remain saved. Only XP above your previous reward is added.</p>@endif
@if($savedResult)
<div class="rounded-xl bg-green-50 p-5" role="status"><p class="font-bold text-primary">Quiz completed — attempt used</p><p class="mt-2 text-xl font-bold">@if($savedResult->score !== null)Saved score: {{ $savedResult->score }} / {{ $savedResult->graded_questions }}@else Answers saved · awaiting teacher review.@endif</p><p class="mt-2 text-sm text-muted">Your answers are saved. Another attempt requires teacher approval of a valid excuse after the activity closes. No third opportunity is allowed.</p></div>
@if($savedResult->xp_awarded_at)
<div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-5"><p class="text-lg font-extrabold text-primary">+{{ $savedResult->xp_credited ?? ($savedResult->answer_xp + $savedResult->perfect_bonus_xp + $savedResult->speed_bonus_xp) }} XP earned</p><p class="mt-2 text-sm font-bold text-red-700">Wrong-answer deduction: −{{ $savedResult->xp_deducted }} XP · {{ $savedResult->wrong_answers }} incorrect/unanswered graded questions</p><p class="mt-2 text-xs">The deduction applies separately from your earned reward. View balance details in Achievements.</p><p class="mt-2 text-sm">{{ $savedResult->score }} correct × 10 XP = {{ $savedResult->answer_xp }} XP</p><p class="mt-1 text-sm">Fast-answer bonus: +{{ $savedResult->speed_bonus_xp }} XP</p><p class="mt-1 text-sm font-bold {{ $savedResult->perfect_bonus_xp ? 'text-amber-800' : 'text-secondary' }}">{{ $savedResult->perfect_bonus_xp ? 'Perfect score! +100 XP bonus awarded.' : 'Perfect-score bonus: not earned.' }}</p></div>
@php($questionResults = json_decode($savedResult->grading_results ?? '[]', true))
@if($questionResults)
<details class="mt-4 rounded-xl border p-4"><summary class="cursor-pointer text-sm font-bold text-primary">XP earned by question</summary><ul class="mt-3 divide-y">@foreach($questionResults as $index => $result)<li class="flex justify-between gap-3 py-3 text-sm"><span>Question {{ $index + 1 }} @if(isset($result['seconds_spent'])) · {{ $result['seconds_spent'] }} seconds @endif · {{ $result['correct'] === null ? 'Awaiting review' : ($result['correct'] ? 'Correct' : 'Incorrect / unanswered') }}</span><b class="text-primary">+{{ ($result['correct'] === true ? 10 : 0) + ($result['speed_bonus_xp'] ?? 0) }} XP @if(($result['speed_bonus_xp'] ?? 0) > 0)<span class="text-xs">(includes +5 fast bonus)</span>@endif</b></li>@endforeach</ul></details>
@endif
@else<p class="mt-3 text-sm text-secondary">XP will be available when the quiz has a graded score.</p>@endif
@elseif($open && $attempt && $attempt->sequential)
@include('quiz-current-question')
@elseif($open && $attempt)
@php($questions = $attempt->questions)
@include('assessment-activity', ['assessmentKind'=>'quiz','assessmentId'=>$attempt->id])
<form method="POST" action="{{ route('student.quiz.submit', $quiz) }}" class="timed-quiz space-y-5" data-draft-url="{{ route('student.quiz.draft', $quiz) }}" data-deadline="{{ $attempt->expires_at->getTimestampMs() }}" data-started="{{ $attempt->started_at->getTimestampMs() }}" data-count="{{ count($questions) }}">
@csrf
<div class="sticky top-16 z-10 rounded-xl border border-green-200 bg-white p-4 shadow-sm"><div class="flex items-center justify-between gap-3"><span class="text-sm font-bold text-primary">Time remaining</span><span class="timer-text font-mono text-xl font-bold text-primary" role="timer">Loading…</span></div><div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-label="Time remaining" aria-valuemin="0" aria-valuemax="100"><div class="timer-bar h-full rounded-full bg-green-700 transition-all duration-700" style="width:100%"></div></div><p class="save-status mt-2 text-xs text-secondary" role="status">Answers save automatically. Refreshing will not restart the timer.</p></div>
@foreach($questions as $index => $question)
@php($answer = $attempt->answers[$index] ?? null)
<fieldset class="rounded-xl bg-paper p-4"><legend class="font-bold text-primary">{{ $index + 1 }}. {{ $question['prompt'] }}</legend>
@if(($question['type'] ?? 'identification') === 'identification')
<input name="answers[{{ $index }}]" maxlength="5000" value="{{ $answer }}" class="mt-3 w-full rounded-xl border p-3" aria-label="Answer to question {{ $index + 1 }}" autocomplete="off">
@else
@if($question['type'] === 'multiple_answer')<p class="text-xs text-secondary">Select all correct answers.</p>@endif
@foreach($question['type'] === 'true_false' ? ['True','False'] : $question['options'] as $option)
<label class="mt-3 flex items-start gap-3 rounded-lg border border-green-100 bg-white p-3"><input class="mt-1 accent-green-800" type="{{ $question['type'] === 'multiple_answer' ? 'checkbox' : 'radio' }}" name="answers[{{ $index }}]{{ $question['type'] === 'multiple_answer' ? '[]' : '' }}" value="{{ $option }}" @checked(is_array($answer) ? in_array($option, $answer, true) : $answer === $option)> {{ $option }}</label>
@endforeach
@endif</fieldset>
@endforeach
<p class="text-xs text-muted">Earn 10 XP per correct answer and 100 bonus XP for a perfect score. Unanswered questions earn zero XP. Multiple-answer questions require exactly the correct choices. Submitting finishes this attempt.</p><button class="submit-quiz rounded-xl bg-primary px-5 py-3 font-bold text-white">Submit & Earn XP →</button>
</form>
@elseif($open)
<p class="mb-4 text-sm leading-6 text-muted">Questions appear one at a time in a random order saved for your attempt. {{ $quiz->untimed ? 'No per-question timer. There is no speed XP on this untimed assessment.' : 'Each question has '.$quiz->question_seconds.' seconds.' }} Save & Next locks your answer; the next question starts immediately. A correct answer within 30 seconds earns 5 extra XP. The displayed closing time still applies.</p><form method="POST" action="{{ route('student.quiz.start', $quiz) }}">@csrf<button class="rounded-xl bg-primary px-5 py-3 font-bold text-white">{{ $quizGrant?->status === 'approved' ? 'Start Final Quiz →' : 'Start Quiz →' }}</button></form>
@else
<p class="rounded-xl bg-paper p-4 text-sm font-semibold text-secondary">{{ $quiz->opens_at && now()->lt($quiz->opens_at) ? 'Not open yet. Come back at the scheduled start time to take this quiz.' : 'This quiz is closed. No new attempts are allowed.' }}</p><a href="{{ route('student.quiz') }}" class="mt-4 inline-block text-sm font-bold text-primary underline">Refresh availability</a>
@endif
@include('student-excuse-request',['excuseType'=>'quiz','excuseActivity'=>$quiz])
</div></section>
@empty<p class="rounded-xl bg-white p-8">Your teacher has not published a quiz for this classroom yet.</p>@endforelse
@include('quiz-timer')
@include('quiz-question-timer')
@include('excuse-status-refresh')
@endsection
