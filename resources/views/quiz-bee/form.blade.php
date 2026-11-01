@extends('layouts.teacher-learning',['pageTitle'=>'Quiz Bee Builder'])
@section('content')
<link rel="stylesheet" href="{{ asset('css/quiz-bee.css') }}?v=2">
@php
    $savedTeams = old('teams', $teams);
    $formTeams = is_array($savedTeams) ? array_slice(array_values($savedTeams), 0, 40) : $teams;
    $bank = old('questions', $bee?->questions ?? config('quiz_bee.questions'));
    if (!is_array($bank)) { $bank = config('quiz_bee.questions'); }
@endphp
<div class="bee-shell"><a class="bee-link" href="{{ route('teacher.quiz-bee.index') }}">← Quiz Bee Arena</a><div class="bee-heading"><div><p class="bee-eyebrow">Teacher-hosted learning</p><h1>{{ $bee ? 'Edit Quiz Bee draft' : 'Create a Quiz Bee' }}</h1><p>Review the C++ examples, select your coders, and publish when ready.</p></div></div>
<p class="bee-notice">Every question has a fixed one-minute limit. Groups use one captain's final answer. Every assigned member earns the same answer XP; the 100 XP perfect bonus is awarded once for getting every regular question correct across all three levels. Tie-breakers award answer XP but are not required for the perfect bonus.</p>
@if($errors->any())<div class="bee-error" role="alert"><strong>Please review these fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form class="bee-form" data-bee-builder method="POST" action="{{ $bee ? route('teacher.quiz-bee.update',$bee) : route('teacher.quiz-bee.store') }}">@csrf @if($bee)@method('PUT')@endif
<section class="bee-card"><label>Match title<input name="title" required maxlength="160" value="{{ old('title',$bee?->title) }}" placeholder="e.g. Grade 8 C++ Quiz Bee"></label><label>Category<select name="category" data-bee-category><option value="individual" @selected(old('category',$bee?->category)==='individual')>Individual · one student per entry</option><option value="group" @selected(old('category',$bee?->category)==='group')>Group · up to five members per team</option></select></label></section>
<section class="bee-card"><div class="bee-heading"><div><h2>Entries & team captains</h2><p>Choose 2–40 entries from your teaching sections. A student may join only one entry in this match.</p></div><button class="bee-button" type="button" data-bee-add-team>＋ Add entry</button></div><div data-bee-teams>@foreach($formTeams as $index=>$team)@include('quiz-bee.team')@endforeach</div><p role="status" data-bee-team-count></p><template data-bee-team-template>@include('quiz-bee.team',['index'=>'__TEAM__','team'=>['name'=>'','captain_id'=>'','members'=>[]]])</template></section>
@foreach(\App\Models\QuizBeeMatch::LEVELS as $level)
<details class="bee-card" @if($level==='easy')open @endif><summary><strong>{{ ucfirst($level) }} question bank</strong> · edit before publishing</summary>
<section data-bee-bank="{{ $level }}"><h2>Regular questions · 1 to 15</h2><p>Starter questions are provided. Check the wording and answer keys for your class.</p><div data-bee-regular>@foreach(is_array($bank[$level]['regular'] ?? null) ? $bank[$level]['regular'] : config('quiz_bee.questions.'.$level.'.regular') as $index=>$question)@include('quiz-bee.question',['kind'=>'regular'])@endforeach</div><button class="bee-button" type="button" data-bee-add-question>＋ Add regular question</button><template data-bee-question-template>@include('quiz-bee.question',['kind'=>'regular','index'=>'__QUESTION__','question'=>['prompt'=>'','options'=>['','','',''],'correct'=>0,'why'=>'']])</template>
<h2>Five tie-breaker questions</h2><p>Only tied leaders answer these, one at a time. The match returns to regular play when the tie is resolved. If all five are exhausted, ties carry to the next level; final unresolved ties share the same rank.</p>
@foreach(is_array($bank[$level]['tiebreak'] ?? null) ? $bank[$level]['tiebreak'] : config('quiz_bee.questions.'.$level.'.tiebreak') as $index=>$question)@include('quiz-bee.question',['kind'=>'tiebreak'])@endforeach
</section></details>
@endforeach
<div class="bee-actions"><button class="bee-button bee-primary" name="status" value="lobby">Publish & send invitations →</button><button class="bee-button" name="status" value="draft">Save draft</button></div><p class="bee-help">Published teams and questions stay fixed for fair play. Captains mark ready in the lobby, then you start and advance questions. Drafts can be edited; cancel and create a new match if a published roster needs to change.</p>
</form></div><script src="{{ asset('js/quiz-bee-builder.js') }}?v=2" defer></script>
@endsection
