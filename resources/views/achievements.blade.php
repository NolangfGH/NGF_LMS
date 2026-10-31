@extends('layouts.student', ['pageTitle'=>'Achievements','active'=>'achievements'])
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div><p class="font-mono text-[10px] font-bold uppercase tracking-widest text-secondary">Your C++ learning journey</p><h1 class="mt-2 text-3xl font-extrabold text-primary">Achievements</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-muted">See what you have accomplished, keep the evidence, and choose your next learning goal.</p></div>
    <a href="{{ route('student.achievements') }}" class="rounded-xl border border-green-200 bg-white px-4 py-3 text-sm font-bold text-primary">Refresh progress ↻</a>
</div>
@if(session('achievement_notice'))<p role="status" class="mb-5 rounded-xl bg-green-100 p-4 text-sm text-primary">{{ session('achievement_notice') }}</p>@endif
@if($errors->any())<p role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</p>@endif
@if($achievementReport['new_count'])
<p role="status" class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-primary">A step forward! {{ $achievementReport['new_count'] }} earned {{ $achievementReport['new_count'] === 1 ? 'badge is' : 'badges are' }} ready to explore in your collection.</p>
@endif
<section class="grid gap-5 lg:grid-cols-[1.1fr_1fr]">
    <article class="relative overflow-hidden rounded-3xl bg-primary p-6 text-white shadow-card sm:p-8">
        <div class="flex items-start justify-between gap-4"><div><p class="font-mono text-[10px] font-bold uppercase tracking-widest text-green-200">Your badge collection</p><p class="mt-4 text-5xl font-extrabold">{{ $achievementReport['earned'] }} <span class="text-xl text-green-200">/ {{ $achievementReport['total'] }}</span></p></div><span class="material-symbols-outlined rounded-2xl bg-white/10 p-4 text-5xl text-lime-200" aria-hidden="true">military_tech</span></div>
        <h2 class="mt-5 text-xl font-bold">{{ $achievementReport['earned'] ? 'Your effort is becoming evidence.' : 'Small steps become strong skills.' }}</h2>
        <p class="mt-3 text-sm leading-6 text-green-100">Badges recognize submitted work, knowledge checks, and skills confirmed in final teacher reviews. Opening assignments or collecting XP alone does not prove a skill.</p>
        <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/20" role="progressbar" aria-label="Badge collection complete" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $achievementReport['completion'] }}"><div class="h-full rounded-full bg-lime-300" style="width:{{ $achievementReport['completion'] }}%"></div></div><p class="mt-2 text-xs font-semibold text-green-200">{{ $achievementReport['completion'] }}% of available milestones earned</p>
    </article>
    <article class="rounded-3xl border border-green-200 bg-white p-6 shadow-card sm:p-8">
        <p class="font-mono text-[10px] font-bold uppercase tracking-widest text-secondary">Your next learning goal</p>
        @if($achievementReport['focus'])
        <h2 class="mt-3 text-2xl font-extrabold text-primary">{{ $achievementReport['focus']['name'] }}</h2>
        <p class="mt-3 text-sm leading-6 text-muted">{{ $achievementReport['focus']['requirement'] }}</p>
        <p class="mt-4 text-sm font-bold text-secondary">{{ min($achievementReport['focus']['current'],$achievementReport['focus']['target']) }} / {{ $achievementReport['focus']['target'] }} {{ $achievementReport['focus']['unit'] }}</p>
        <p class="mt-3 rounded-xl bg-paper p-3 text-xs leading-6 text-muted">{{ $achievementReport['focus']['next_step'] }}</p>
        <a href="{{ route($achievementReport['focus']['route']) }}" class="mt-4 inline-block rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Work toward this goal →</a>
        <form method="POST" action="{{ route('student.achievements.goal') }}" class="mt-3">@csrf<button class="text-xs font-bold text-secondary underline">Choose a suggested next goal</button></form>
        @else
        <h2 class="mt-3 text-2xl font-extrabold text-primary">Every milestone earned!</h2><p class="mt-3 text-sm leading-6 text-muted">Keep applying your C++ skills to new problems. Read teacher feedback and explain how your solutions work.</p><a href="{{ route('student.assignments') }}" class="mt-5 inline-block rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Explore assignments →</a>
        @endif
    </article>
</section>
<section class="my-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Saved learning activity">
    @foreach([['Different quizzes',$achievementReport['metrics']['quizzes_completed']],['Coding activities submitted',$achievementReport['metrics']['submitted_activities']],['Activities reviewed by teacher',$achievementReport['reviewed_activities']],['Learning days (UTC+08:00)',$achievementReport['metrics']['active_days']]] as [$label,$value])
    <article class="rounded-2xl border border-black/5 bg-white p-5 shadow-card"><p class="text-xs font-bold text-secondary">{{ $label }}</p><strong class="mt-2 block text-3xl text-primary">{{ $value }}</strong></article>
    @endforeach
</section>
@if($achievementReport['awaiting_review'])
<p class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">{{ $achievementReport['awaiting_review'] }} {{ $achievementReport['awaiting_review'] === 1 ? 'submission is' : 'submissions are' }} awaiting teacher review. Skill badges unlock from final teacher rubric scores, including when AI helped prepare the draft.</p>
@endif
<section class="rounded-2xl border border-green-100 bg-white p-5">
    <div class="flex flex-wrap items-end gap-3"><label class="min-w-40 flex-1 text-xs font-bold text-secondary">Find a badge<input id="achievementSearch" type="search" placeholder="Search badge or requirement…" class="mt-2 w-full rounded-xl border bg-paper p-3 text-sm"></label><label class="text-xs font-bold text-secondary">Status<select id="achievementStatus" class="mt-2 block rounded-xl border bg-white p-3 text-sm"><option value="all">All milestones</option><option value="earned">Earned</option><option value="progress">Not yet earned</option></select></label><label class="text-xs font-bold text-secondary">Learning area<select id="achievementCategory" class="mt-2 block rounded-xl border bg-white p-3 text-sm"><option value="all">All areas</option><option value="coding">Coding</option><option value="quizzes">Quizzes</option><option value="growth">Growth</option></select></label><button id="clearAchievementFilters" type="button" class="rounded-xl border p-3 text-sm font-bold text-primary">Clear</button></div>
    <p id="achievementResultCount" role="status" class="mt-3 text-xs text-secondary"></p>
</section>
<section class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3" aria-label="Learning badge collection">
@foreach($achievementReport['cards']->sortByDesc('earned') as $badge)
    @include('achievement-card')
@endforeach
</section>
<p id="achievementNoMatches" hidden class="mt-5 rounded-xl bg-white p-8 text-center text-sm text-muted">No milestones match these filters. Try another learning area or clear your search.</p>
<section class="mt-6 rounded-2xl border border-green-100 bg-white p-5"><h2 class="font-bold text-primary">How your achievements work</h2><p class="mt-2 text-sm leading-6 text-muted">Progress uses your saved activity. Revisions and final quiz opportunities do not count as extra activities. Earned badges stay in your history, and they do not add or spend XP. Days do not need to be consecutive. Your teacher can use this evidence to plan feedback and support.</p><a href="{{ route('student.progress') }}" class="mt-3 inline-block text-sm font-bold text-primary underline">View my learning progress →</a></section>
@include('achievements-script')
@if($achievementReport['new_count'] > 0)<script>window.addEventListener('load',()=>window.dispatchEvent(new CustomEvent('codequest:celebrate')));</script>@endif
@endsection

