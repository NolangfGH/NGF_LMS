@extends('layouts.student',['pageTitle'=>'Challenges','active'=>'challenges'])
@section('content')
<section class="mb-6 rounded-3xl border border-green-200 bg-white p-6 shadow-card"><p class="text-xs font-bold uppercase tracking-widest text-secondary">Quiz Bee invitations</p><h2 class="mt-2 text-2xl font-extrabold text-primary">Your Quiz Bee arena</h2><p class="mt-3 text-sm leading-6">Join your teacher's individual or group matches. Correct answers earn 10 XP, answers within 30 seconds earn 5 extra XP, and a perfect regular-question match earns 100 bonus XP.</p><a href="{{ route('student.quiz-bee.index') }}" class="mt-4 inline-block rounded-xl bg-primary px-5 py-3 font-bold text-white">View my Quiz Bee matches →</a></section>
<section class="mb-7 rounded-3xl border border-green-200 bg-white p-5 sm:p-7" aria-label="Two-student coding challenges">
<p class="font-mono text-xs font-bold uppercase tracking-widest text-secondary">Teacher invitations · Shared timer</p><h2 class="mt-2 text-2xl font-extrabold text-primary">Your coding matches</h2>
<p class="mt-2 mb-5 text-sm leading-6 text-muted">Accept an invitation to compete with another coder. Your teacher verifies submitted solutions and awards the first correct one. Your draft stays private from your opponent.</p>
<div data-match-records="{{ route('student.matches.records',['page'=>request('page',1)]) }}">@include('match-records',['matchRole'=>'student'])</div>
</section>
<script src="{{ asset('js/match-records.js') }}" defer></script>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4"><div><p class="font-mono text-[10px] font-bold uppercase tracking-widest text-secondary">Your C++ practice arena</p><h1 class="mt-2 text-3xl font-extrabold text-primary">Challenges</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-muted">Choose a problem, build a solution, and improve it with teacher feedback. Your code and learning evidence stay with your account.</p></div><a href="{{ route('student.achievements') }}" class="rounded-xl border border-green-200 bg-white px-4 py-3 text-sm font-bold text-primary">View learning badges →</a></div>
@if(session('excuse_notice'))<p role="status" class="mb-5 rounded-xl bg-green-100 p-4 text-sm">{{ session('excuse_notice') }}</p>@endif
@if($errors->any())<p role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</p>@endif
<p class="mb-4 text-right"><a href="{{ route('student.challenges') }}" class="text-xs font-bold text-primary underline">Refresh missions ↻</a></p>
<section class="mb-6 grid gap-5 lg:grid-cols-[1.3fr_1fr]">
    <article class="rounded-3xl bg-primary p-7 text-white"><p class="font-mono text-[10px] font-bold uppercase tracking-widest text-green-200">Think · Code · Test · Explain</p><h2 class="mt-3 text-2xl font-extrabold">Turn an idea into a working solution.</h2><p class="mt-4 text-sm leading-7 text-green-100">Start with output and variables, then explore conditions, loops, functions, and input. Save your draft in Code Lab and submit your explanation for teacher review.</p><div class="mt-5 flex flex-wrap gap-3 text-xs font-bold"><span class="rounded-full bg-white/10 px-3 py-2">Personal saved progress</span><span class="rounded-full bg-white/10 px-3 py-2">Learning evidence</span><span class="rounded-full bg-white/10 px-3 py-2">Teacher feedback</span></div></article>
    <article class="rounded-3xl border border-green-200 bg-white p-6"><h2 class="text-xl font-bold text-primary">What counts as completed?</h2><p class="mt-3 text-sm leading-7 text-muted">Your teacher confirms a strong solution with at least 3/4 for both correctness and logic in a final review. AI drafts and opening an activity do not mark it completed.</p><p class="mt-3 rounded-xl bg-paper p-3 text-xs leading-6 text-secondary">Teacher challenge XP is credited once on first opening, following your class reward rules. Guided practice gives feedback and badge progress without opening XP.</p><a href="{{ route('student.progress') }}" class="mt-4 inline-block text-sm font-bold text-primary underline">See my progress →</a></article>
</section>
<section class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Challenge progress">
    @foreach([['Missions in your collection',count($challengeCards)],['Teacher-confirmed',collect($challengeCards)->where('progress.status','completed')->count()],['Drafts saved',collect($challengeCards)->where('progress.status','draft')->count()],['Awaiting review',collect($challengeCards)->where('progress.status','awaiting_review')->count()]] as [$label,$value])
    <article class="rounded-2xl bg-white p-5 shadow-card"><p class="text-xs font-bold text-secondary">{{ $label }}</p><strong class="mt-2 block text-3xl text-primary">{{ $value }}</strong></article>
    @endforeach
</section>
@if(collect($challengeCards)->where('source','teacher')->isEmpty())
<p class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm leading-6 text-secondary">Your teacher has not published a challenge for your section yet. You can start guided practice now; teacher challenges will appear here when published.</p>
@endif
<section class="rounded-2xl border border-green-100 bg-white p-5">
<div class="flex flex-wrap items-end gap-3">
<label class="min-w-40 flex-1 text-xs font-bold text-secondary">Find a mission<input id="challengeSearch" type="search" placeholder="Search topics or titles…" class="mt-2 w-full rounded-xl border bg-paper p-3 text-sm"></label>
<label class="text-xs font-bold text-secondary">Source<select id="challengeSource" class="mt-2 block rounded-xl border bg-white p-3 text-sm"><option value="all">All missions</option><option value="teacher">My teacher</option><option value="practice">Guided practice</option></select></label>
<label class="text-xs font-bold text-secondary">Difficulty<select id="challengeDifficulty" class="mt-2 block rounded-xl border bg-white p-3 text-sm"><option value="all">All levels</option><option>Easy</option><option>Mid</option><option>Hard</option></select></label>
<label class="text-xs font-bold text-secondary">Progress<select id="challengeStatus" class="mt-2 block rounded-xl border bg-white p-3 text-sm"><option value="all">All progress</option><option value="not_started">Ready to start</option><option value="closed">Closed</option><option value="draft">Draft saved</option><option value="awaiting_review">Awaiting review</option><option value="improve">Improve with feedback</option><option value="completed">Teacher-confirmed</option></select></label>
<button id="clearChallengeFilters" type="button" class="rounded-xl border p-3 text-sm font-bold text-primary">Clear</button>
</div><p id="challengeResultCount" role="status" class="mt-3 text-xs text-secondary"></p>
</section>
<section class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3" aria-label="Challenge missions">
@foreach($challengeCards as $card)
    @include('challenge-card')
@endforeach
</section>
<p id="challengeNoMatches" hidden class="mt-5 rounded-xl bg-white p-8 text-center text-sm text-muted">No challenges match these filters. Clear your search or choose another difficulty.</p>
@include('challenges-script')
@include('excuse-status-refresh')
@endsection
