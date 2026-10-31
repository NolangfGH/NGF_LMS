<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Quiz answers | CodeQuest</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"><script src="https://cdn.tailwindcss.com"></script>@include('teacher-preferences')@include('brand-head')</head>
<body class="app-admin-standalone bg-green-50 text-green-950">@include('admin-page-header')<main class="mx-auto max-w-4xl px-6 pb-6 pt-24">@include('challenge-notifications',['noticeRole'=>'teacher'])
@include('teacher-excuse-link')
<a href="{{ route('teacher.quizzes') }}">← Quiz Builder</a><h1 class="my-6 text-3xl font-bold">Student quiz answers</h1>
@forelse($submissions as $submission)
<article id="quiz-submission-{{ $submission->id }}" class="scroll-mt-24 mb-6 rounded-2xl bg-white p-6">
<h2 class="text-xl font-bold">{{ $submission->title }} · {{ $submission->nickname }}</h2>
<p class="mt-2 text-sm font-bold">{{ $submission->attempt_number === 2 ? 'Second and final opportunity' : 'Original attempt' }} · {{ $submission->xp_credited ?? 0 }} additional XP credited</p>
<p class="my-3 text-sm">Saved {{ $submission->updated_at }}</p>
@if($submission->score !== null)
<p class="rounded-xl bg-green-100 p-3 font-bold">Score: {{ $submission->score }} / {{ $submission->graded_questions }}
@if($submission->graded_questions < $submission->total_questions) · {{ $submission->total_questions - $submission->graded_questions }} questions need review.@endif</p>
@else<p>Awaiting answer key / teacher review</p>@endif
@php($results = json_decode($submission->grading_results ?? '[]', true))
@if($results)
@foreach($results as $result)
<section class="mt-5 border-t pt-3"><h3 class="font-bold">{{ $result['prompt'] }}</h3>
<p class="whitespace-pre-wrap">Student answer: {{ is_array($result['answer']) ? implode(', ', $result['answer']) : $result['answer'] }}</p>
@if(isset($result['seconds_spent']))<p>Time spent: {{ $result['seconds_spent'] }} seconds · Fast-answer XP: +{{ $result['speed_bonus_xp'] ?? 0 }}</p>@endif
<p>Correct answer: {{ implode(', ', $result['expected']) ?: 'Not provided' }}</p>
<p class="font-bold {{ $result['correct'] === false ? 'text-red-700' : 'text-green-800' }}">{{ $result['correct'] === null ? 'Needs review' : ($result['correct'] ? 'Correct · 1 point' : 'Incorrect · 0 points') }}</p></section>
@endforeach
@else
@foreach(json_decode($submission->questions, true) as $index => $question)
@php($answer = json_decode($submission->answers, true)[$index] ?? 'No answer')
<h3 class="mt-4 font-bold">{{ $question['prompt'] }}</h3><p class="whitespace-pre-wrap">{{ is_array($answer) ? implode(', ', $answer) : $answer }}</p>
@endforeach
@endif
</article>
@empty<p>No quiz submissions yet.</p>@endforelse
</main></body></html>
