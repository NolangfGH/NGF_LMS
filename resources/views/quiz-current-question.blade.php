@php
    $certification = isset($examQuestion);
    $index = $certification ? $examQuestion['index'] : $attempt->current_question;
    $question = $certification ? $examQuestion['question'] : $attempt->questions[$index];
    $answer = $certification ? $examQuestion['answer'] : ($attempt->answers[$index] ?? null);
    $attemptId = $certification ? $examQuestion['id'] : $attempt->id;
    $seconds = $certification ? $examQuestion['seconds'] : $attempt->question_seconds;
    $untimed = !$certification && $attempt->untimed;
    $deadline = $certification ? $examQuestion['deadline'] : $attempt->questionDeadline()->getTimestampMs();
    $total = $certification ? \App\Services\CoursePostTest::QUESTIONS : count($attempt->questions);
    $answerUrl = $certification ? route('student.post-test.question', $courseQuery ?? []) : route('student.quiz.question', $quiz);
    $draftUrl = $certification ? route('student.post-test.draft', $courseQuery ?? []) : route('student.quiz.draft', $quiz);
    $refreshUrl = $certification ? route('student.post-test.show', $courseQuery ?? []) : route('student.quiz');
    $watermark = strtoupper(substr(hash_hmac('sha256', ($certification ? 'post-test:' : 'quiz:').$attemptId, (string) config('app.key')), 0, 10));
@endphp
@include('assessment-activity', ['assessmentKind' => $certification ? 'post_test' : 'quiz', 'assessmentId' => $attemptId])
<form method="POST" action="{{ $answerUrl }}" class="question-quiz min-w-0 space-y-4" data-untimed="{{ $untimed ? 'true':'false' }}" data-seconds="{{ $seconds }}" data-draft-url="{{ $draftUrl }}" data-deadline="{{ $deadline }}" data-index="{{ $index }}" data-server-time="{{ now()->getTimestampMs() }}">
@csrf<input type="hidden" name="question_index" value="{{ $index }}"><input type="hidden" name="attempt_id" value="{{ $attemptId }}">
<div class="sticky top-16 z-10 rounded-2xl border border-green-200 bg-green-50 p-3 sm:p-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div><p class="text-xs font-bold uppercase tracking-widest text-secondary">Question {{ $index + 1 }} of {{ $total }}</p><p class="mt-1 text-xs sm:text-sm">{{ $untimed ? 'Untimed assessment' : $seconds.' seconds per question' }}</p></div>
        <span class="question-time font-mono text-2xl font-extrabold text-primary sm:text-3xl" role="timer">{{ $untimed ? 'Untimed' : gmdate('i:s', $seconds) }}</span>
    </div>
    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-green-100" role="progressbar" aria-label="Question time remaining" aria-valuemin="0" aria-valuemax="{{ $seconds }}" @if($untimed) hidden @endif><div class="question-time-bar h-full rounded-full bg-green-700 motion-safe:transition-all" style="width:100%"></div></div>
    @unless($certification)<p class="mt-2 text-xs font-semibold text-amber-800">Correct answer earns 10 XP @unless($untimed) · 5 extra XP within 30 seconds @endunless</p>@endunless
    <p class="question-save-status mt-2 text-xs text-secondary" role="status">Your answer saves as you work.</p>
</div>
<div data-assessment-content class="min-w-0 rounded-2xl border bg-paper p-3 sm:p-5">
<p class="mb-3 text-xs font-bold tracking-wide text-secondary" aria-label="Exam attempt watermark">NGF C++ · {{ $watermark }}</p>
<fieldset class="min-w-0">
<legend class="max-w-full whitespace-pre-wrap break-words text-base font-bold text-primary sm:text-lg">{{ $question['prompt'] }}</legend>
@if(($question['type'] ?? '') === 'ordering')
<p class="mb-3 text-sm">Put the steps in order. Drag a step or use its Up / Down buttons.</p><div data-ordering class="space-y-3">@foreach(is_array($answer) && count($answer) === count($question['options']) ? $answer : $question['options'] as $step)<div draggable="true" data-order-step class="flex items-center gap-3 rounded-xl border bg-white p-3"><input type="hidden" name="answer[]" value="{{ $step }}"><span class="flex-1 whitespace-pre-wrap text-sm">{{ $step }}</span><button type="button" data-order-up aria-label="Move step up" class="rounded-lg border px-3 py-2">↑</button><button type="button" data-order-down aria-label="Move step down" class="rounded-lg border px-3 py-2">↓</button></div>@endforeach</div><script src="{{ asset('js/quiz-ordering.js') }}" defer></script>
@elseif(in_array($question['type'] ?? 'identification',['identification','code_output','fill_code','debugging']))
<label class="block"><span class="text-sm font-bold">Your answer</span><input name="answer" maxlength="5000" value="{{ $answer }}" class="mt-2 w-full rounded-xl border bg-white p-4" autocomplete="off"></label>
@else
@if($question['type'] === 'multiple_answer')<p class="mb-3 text-sm text-secondary">Select all correct answers.</p>@endif
@foreach($question['type'] === 'true_false' ? ['True','False'] : $question['options'] as $option)
<label class="mt-3 flex min-h-12 min-w-0 items-start gap-3 rounded-xl border border-green-100 bg-white p-3 has-[:checked]:border-green-700 has-[:checked]:bg-green-50 sm:p-4"><input class="mt-1 shrink-0 accent-green-800" type="{{ $question['type'] === 'multiple_answer' ? 'checkbox' : 'radio' }}" name="answer{{ $question['type'] === 'multiple_answer' ? '[]' : '' }}" value="{{ $option }}" @checked(is_array($answer) ? in_array($option,$answer,true) : $answer === $option)> <span class="min-w-0 whitespace-pre-wrap break-words [overflow-wrap:anywhere]">{{ $option }}</span></label>
@endforeach
@endif</fieldset>
<p class="mt-4 text-xs leading-5 text-muted">Saving moves to the next question and locks this answer. At the deadline, the last saved answer is locked.</p>
<button class="question-next mt-3 w-full rounded-xl bg-primary px-5 py-3 font-bold text-white disabled:opacity-50 sm:w-auto">{{ $index + 1 === $total ? ($certification ? 'Save & Finish Exam' : 'Save & Finish Quiz') : 'Save & Next Question' }} →</button>
</div>
<a href="{{ $refreshUrl }}" class="hidden question-reload text-sm font-bold text-primary underline">Refresh to continue</a>
</form>
