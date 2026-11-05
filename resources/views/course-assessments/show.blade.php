@extends('layouts.student', ['pageTitle' => $title, 'active' => 'assessments'])

@section('content')
<a href="{{ route('student.assessments.index') }}" class="text-sm font-bold text-primary">← Course assessments</a>
<h1 class="mb-3 mt-4 text-3xl font-extrabold text-primary">{{ $title }}</h1>
@if($assessment === 'reasoning')
    <p class="mb-5 max-w-3xl text-sm leading-6">This 50-question Grade 8 practice exercise reports your performance on these items. The skill breakdown is for classroom practice and personal reflection.</p>
@endif
@if($locked)
    <div class="rounded-2xl border border-green-100 bg-white p-6">
        <h2 class="text-lg font-extrabold text-primary">Post-test locked</h2>
        <p class="mt-2 text-sm leading-6">Submit the pre-test and complete all {{ $courseProgress['total'] }} core checkpoints. You’ve completed {{ $courseProgress['completed'] }} so far.</p>
        <a href="{{ $pretest ? route('student.learn') : route('student.assessments.show', 'pre-test') }}" class="mt-4 inline-block rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">{{ $pretest ? 'Continue lessons' : 'Open pre-test' }} →</a>
    </div>
@elseif($result)
    <div class="rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <p class="text-xs font-bold uppercase text-secondary">Saved result · {{ \Illuminate\Support\Carbon::parse($result->completed_at)->timezone(config('learning.timezone'))->format('M j, Y') }}</p>
        <h2 class="mt-2 text-3xl font-extrabold text-primary">{{ $result->score }} / {{ $result->total_questions }} correct</h2>
        @if($assessment === 'pre-test')
            <p class="mt-3 text-sm">Your pre-test is complete. You can now open the C++ lessons.</p>
            <a href="{{ route('student.learn') }}" class="mt-4 inline-block rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Open the learning path →</a>
        @elseif($assessment === 'post-test')
            @if($pretest)
                @php($change = round(100 * ($result->score / $result->total_questions - $pretest->score / $pretest->total_questions), 1))
                <p class="mt-3 text-sm">Pre-test {{ $pretest->score }} / {{ $pretest->total_questions }} · Post-test {{ $result->score }} / {{ $result->total_questions }} · {{ $change > 0 ? '+' : '' }}{{ $change }} percentage points across the two tests</p>
            @endif
            <a href="{{ route('student.assessments.certificate') }}" class="mt-4 inline-block rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Open completion certificate →</a>
        @else
            <p class="mt-3 text-sm font-bold text-primary">{{ \App\Services\CourseAssessments::practiceBand($result->score, $result->total_questions) }}</p>
            <p class="mt-2 text-sm leading-6">Practise the areas with the fewest correct answers first. For each area and the overall result, 90–100% suggests a stretch task, 70–89% is secure, 40–69% is developing, and below 40% suggests starting with guided examples.</p>
        @endif
    </div>
    <h2 class="mb-4 mt-7 text-xl font-extrabold text-primary">{{ $assessment === 'reasoning' ? 'Skill breakdown' : 'Results by term' }}</h2>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($skills as $skill => $detail)
            <article class="rounded-2xl border border-green-100 bg-white p-5">
                <h3 class="font-bold text-primary">{{ $skill }}</h3>
                <p class="mt-2 text-lg font-extrabold">{{ $detail['score'] }} / {{ $detail['total'] }}</p>
                <progress class="mt-2 h-2 w-full accent-green-800" value="{{ $detail['score'] }}" max="{{ $detail['total'] }}" aria-label="{{ $skill }} score"></progress>
                @if($assessment === 'reasoning')<p class="mt-2 text-xs font-bold text-secondary">{{ \App\Services\CourseAssessments::practiceBand($detail['score'], $detail['total']) }}</p>@endif
            </article>
        @endforeach
    </div>
@else
    <div class="mb-5 rounded-xl border border-green-200 bg-white p-4 text-sm leading-6">
        <p>Answer all {{ count($questions) }} questions in one sitting. There’s no timer. Your result is saved when you submit.</p>
        @if($assessment === 'reasoning')<p>Each correct answer earns one point. For select-all questions, choose every correct option to earn the point.</p>@endif
    </div>
    @if($errors->any())
        <div role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-sm font-bold text-red-800">Review the highlighted questions, then submit again.</div>
    @endif
    <form method="POST" action="{{ route('student.assessments.submit', $assessment) }}" class="space-y-4">
        @csrf
        @foreach($questions as $index => $question)
            @if($loop->first || $question['skill'] !== $questions[$index - 1]['skill'])
                <h2 class="pt-4 text-xl font-extrabold text-primary">{{ $question['skill'] }}</h2>
            @endif
            <fieldset class="rounded-xl border {{ $errors->has('answers.'.$index) || $errors->has('answers.'.$index.'.*') ? 'border-red-400' : 'border-green-100' }} bg-white px-5 pb-5 pt-3">
                <legend class="max-w-full break-words px-1 text-sm font-bold leading-6 text-primary">{{ $loop->iteration }}. {{ $question['prompt'] }}</legend>
                @php($field = 'answers.'.$index)
                @if($question['type'] === 'identification')
                    <label for="answer-{{ $index }}" class="text-xs font-bold text-secondary">Your answer</label>
                    <input id="answer-{{ $index }}" name="answers[{{ $index }}]" value="{{ is_string(old($field)) ? old($field) : '' }}" inputmode="numeric" maxlength="80" required class="mt-1 block w-full max-w-xs rounded-lg border border-green-200 p-3 text-sm">
                @else
                    @php($multi = $question['type'] === 'multiple_answer')
                    @if($multi)<p class="mb-2 text-xs text-secondary">Select all that apply.</p>@endif
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach($question['options'] as $option)
                            <label class="flex items-start gap-2 rounded-lg bg-paper p-3 text-sm">
                                <input type="{{ $multi ? 'checkbox' : 'radio' }}" name="answers[{{ $index }}]{{ $multi ? '[]' : '' }}" value="{{ $option }}" @checked($multi ? in_array($option, (array) old($field), true) : old($field) === $option) @required(!$multi) class="mt-0.5 accent-green-800">
                                <span class="min-w-0 break-words">{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @if($errors->has($field) || $errors->has($field.'.*'))<p class="mt-2 text-xs font-bold text-red-700">Enter or select a valid answer for this question.</p>@endif
            </fieldset>
        @endforeach
        <button class="rounded-xl bg-primary px-5 py-3 font-bold text-white">Submit answers</button>
    </form>
@endif
@endsection
