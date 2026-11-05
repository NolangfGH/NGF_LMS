@extends('layouts.teacher-learning')

@section('content')
<a href="{{ route('school.ilaw.index') }}" class="text-sm font-bold text-primary">← Lesson plan reviews</a>
<h1 class="mt-4 text-3xl font-extrabold text-primary">{{ $content['title'] }}</h1>
<p class="mt-2 text-sm">{{ $plan->first_name }} {{ $plan->last_name }} · {{ $teacher->school }}</p>
<div class="mt-6 grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
    <article class="space-y-6 rounded-2xl border border-green-100 bg-white p-5 text-sm leading-6 sm:p-7">
        @include('ilaw.content')
    </article>
    <aside class="rounded-2xl border border-green-100 bg-white p-5">
        @if($plan->status === 'submitted')
            <h2 class="font-extrabold text-primary">Evaluate lesson plan</h2>
            <p class="my-3 text-sm leading-6">Check how the objectives, learning activities, evidence and next steps fit together.</p>
            <p class="mb-4 text-xs"><a href="{{ \App\Services\IlawPlanTemplate::ORDER_URL }}" target="_blank" rel="noopener" class="font-bold text-primary underline">Open the coaching rubric in Annex A</a></p>
            <form method="POST" action="{{ route('school.ilaw.evaluate', $plan->id) }}">
                @csrf
                <input type="hidden" name="revision" value="{{ old('revision', $plan->revision) }}">
                <label for="review-comment" class="text-sm font-bold text-primary">Suggestion or comment (optional)</label>
                <textarea id="review-comment" name="comment" maxlength="3000" rows="5" class="mt-2 w-full rounded-xl border border-green-200 p-3 text-sm">{{ old('comment') }}</textarea>
                <button class="mt-3 rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Mark evaluated &amp; notify teacher</button>
            </form>
        @else
            <h2 class="font-extrabold text-primary">Evaluated</h2>
            <p class="mt-2 text-sm">{{ \Illuminate\Support\Carbon::parse($plan->evaluated_at)->timezone(config('learning.timezone'))->format('M j, Y g:i A') }}</p>
            @if($plan->review_comment)<p class="mt-3 whitespace-pre-wrap break-words text-sm">{{ $plan->review_comment }}</p>@endif
        @endif
    </aside>
</div>
@endsection
