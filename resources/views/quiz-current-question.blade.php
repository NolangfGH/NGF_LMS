@php
    $index = $attempt->current_question;
    $question = $attempt->questions[$index];
    $answer = $attempt->answers[$index] ?? null;
@endphp
<form method="POST" action="{{ route('student.quiz.question', $quiz) }}" class="question-quiz space-y-5" data-untimed="{{ $attempt->untimed ? 'true':'false' }}" data-seconds="{{ $attempt->question_seconds }}" data-draft-url="{{ route('student.quiz.draft', $quiz) }}" data-deadline="{{ $attempt->questionDeadline()->getTimestampMs() }}" data-index="{{ $index }}">
@csrf<input type="hidden" name="question_index" value="{{ $index }}"><input type="hidden" name="attempt_id" value="{{ $attempt->id }}">
<div class="rounded-2xl border border-green-200 bg-green-50 p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-widest text-secondary">Question {{ $index + 1 }} of {{ count($attempt->questions) }}</p><p class="mt-1 text-sm">{{ $attempt->untimed ? 'Untimed assessment' : $attempt->question_seconds.' seconds per question' }} · Random question order</p></div><span class="question-time font-mono text-3xl font-extrabold text-primary" role="timer">01:00</span></div>
<div class="mt-4 h-3 overflow-hidden rounded-full bg-green-100" role="progressbar" aria-label="Question time remaining" aria-valuemin="0" aria-valuemax="60"><div class="question-time-bar h-full rounded-full bg-green-700 transition-all duration-300" style="width:100%"></div></div>
<p class="mt-3 text-xs font-semibold text-amber-800">Correct answer: +10 XP @unless($attempt->untimed) · Answer correctly within 30 seconds: +5 extra XP @endunless</p>
<p class="question-save-status mt-2 text-xs text-secondary" role="status">Answers save automatically. The timer continues when you refresh.</p></div>
<fieldset class="rounded-2xl border bg-paper p-5"><legend class="whitespace-pre-wrap px-2 text-lg font-bold text-primary">{{ $question['prompt'] }}</legend>
@if(($question['type'] ?? '') === 'ordering')
<p class="mb-3 text-sm">Put the steps in order. Drag a step or use its Up / Down buttons.</p><div data-ordering class="space-y-3">@foreach(is_array($answer) && count($answer) === count($question['options']) ? $answer : $question['options'] as $step)<div draggable="true" data-order-step class="flex items-center gap-3 rounded-xl border bg-white p-3"><input type="hidden" name="answer[]" value="{{ $step }}"><span class="flex-1 whitespace-pre-wrap text-sm">{{ $step }}</span><button type="button" data-order-up aria-label="Move step up" class="rounded-lg border px-3 py-2">↑</button><button type="button" data-order-down aria-label="Move step down" class="rounded-lg border px-3 py-2">↓</button></div>@endforeach</div><script src="{{ asset('js/quiz-ordering.js') }}" defer></script>
@elseif(in_array($question['type'] ?? 'identification',['identification','code_output','fill_code','debugging']))
<label class="block"><span class="text-sm font-bold">Your answer</span><input name="answer" maxlength="5000" value="{{ $answer }}" class="mt-2 w-full rounded-xl border bg-white p-4" autocomplete="off"></label>
@else
@if($question['type'] === 'multiple_answer')<p class="mb-3 text-sm text-secondary">Select all correct answers.</p>@endif
@foreach($question['type'] === 'true_false' ? ['True','False'] : $question['options'] as $option)
<label class="mb-3 flex items-start gap-3 rounded-xl border border-green-100 bg-white p-4"><input class="mt-1 accent-green-800" type="{{ $question['type'] === 'multiple_answer' ? 'checkbox' : 'radio' }}" name="answer{{ $question['type'] === 'multiple_answer' ? '[]' : '' }}" value="{{ $option }}" @checked(is_array($answer) ? in_array($option,$answer,true) : $answer === $option)> <span>{{ $option }}</span></label>
@endforeach
@endif</fieldset>
<p class="text-xs leading-5 text-muted">Save & Next locks this answer. You cannot go back. When time expires, the last saved answer is locked and the next question opens. Time spent is recorded by the server.</p>
<button class="question-next rounded-xl bg-primary px-5 py-3 font-bold text-white">{{ $index + 1 === count($attempt->questions) ? 'Save & Finish Quiz' : 'Save & Next Question' }} →</button>
<a href="{{ route('student.quiz') }}" class="hidden question-reload text-sm font-bold text-primary underline">Refresh to continue</a>
</form>
