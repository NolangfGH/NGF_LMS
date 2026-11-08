<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student Achievements | CodeQuest</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}"><style>body{font-family:'Plus Jakarta Sans',sans-serif}</style>@include('teacher-preferences')@include('brand-head')</head>
<body class="app-admin-standalone bg-[#f6fbee] text-green-950">@include('admin-page-header')<main class="mx-auto max-w-6xl px-5 pb-8 pt-24">@include('challenge-notifications',['noticeRole'=>'teacher'])
<a href="{{ route('teacher.dashboard') }}" class="text-sm font-bold">← Teacher Dashboard</a>
<div class="my-6 flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest text-green-700">Evidence of learning</p><h1 class="mt-2 text-3xl font-extrabold">Student achievements</h1><p class="mt-3 max-w-2xl text-sm leading-6">Recognize progress, see each learner's next goal, and use reviewed work to plan support.</p></div><a href="{{ route('teacher.achievements.export',request()->only('classroom','search')) }}" class="rounded-xl bg-green-900 px-4 py-3 text-sm font-bold text-white">Export filtered CSV</a></div>
<form method="GET" class="mb-6 flex flex-wrap items-end gap-4 rounded-2xl border border-green-100 bg-white p-5">
<label class="min-w-48 flex-1 text-sm font-bold">Grade and section<select name="classroom" class="mt-2 w-full rounded-xl border bg-white p-3 text-sm"><option value="">All my sections</option>@foreach($rooms as $room)<option value="{{ $room->id }}" @selected($selected === $room->id)>{{ $room->grade }} · {{ $room->section ?: $room->name }}</option>@endforeach</select></label>
<label class="min-w-48 flex-1 text-sm font-bold">Find a learner<input type="search" name="search" value="{{ $search }}" maxlength="100" placeholder="Nickname or student ID" class="mt-2 w-full rounded-xl border p-3 text-sm"></label><button class="rounded-xl bg-green-900 px-4 py-3 text-sm font-bold text-white">Apply filters</button><a href="{{ route('teacher.achievements') }}" class="px-3 py-3 text-sm font-bold underline">Clear</a>
</form>
<section class="mb-6 grid gap-4 sm:grid-cols-3"><article class="rounded-2xl bg-white p-5"><p class="text-xs font-bold uppercase text-green-700">Learners in selection</p><strong class="mt-2 block text-3xl">{{ $students->total() }}</strong></article><article class="rounded-2xl bg-white p-5"><p class="text-xs font-bold uppercase text-green-700">Milestones available</p><strong class="mt-2 block text-3xl">{{ count($catalog) }}</strong></article><article class="rounded-2xl bg-white p-5"><p class="text-xs font-bold uppercase text-green-700">Awaiting review on this page</p><strong class="mt-2 block text-3xl">{{ $reports->sum('awaiting_review') }}</strong></article></section>
<p class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm leading-6">Coding skill badges require a final teacher review. AI suggestions do not count until you save your decision. Final opportunities and revisions do not count as extra activities.</p>
<section class="space-y-5">
@forelse($students as $learner)
    @php
        $report=$reports->get($learner->id);
    @endphp
    <article class="rounded-2xl border border-green-100 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-xl font-bold">{{ $learner->nickname }}</h2><p class="mt-1 text-xs text-green-800">{{ $learner->student_id }} · {{ $learner->classroom->grade }} · {{ $learner->classroom->section ?: $learner->classroom->name }}</p></div><span class="rounded-full bg-green-100 px-4 py-2 text-sm font-bold">{{ $report['earned'] }} / {{ $report['total'] }} badges earned</span></div>
        <div class="mt-4 grid gap-3 text-sm sm:grid-cols-4"><p class="rounded-xl bg-green-50 p-3"><b>{{ $report['metrics']['quizzes_completed'] }}</b> quizzes completed</p><p class="rounded-xl bg-green-50 p-3"><b>{{ $report['metrics']['submitted_activities'] }}</b> coding activities submitted</p><p class="rounded-xl bg-green-50 p-3"><b>{{ $report['metrics']['strong_solutions'] }}</b> strong solutions reviewed</p><p class="rounded-xl bg-green-50 p-3"><b>{{ $report['metrics']['active_days'] }}</b> learning days</p></div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-green-200 bg-green-50 p-4"><p class="font-bold">{{ number_format($learner->xp) }} XP · Level {{ $learner->level }}</p><a href="{{ route('teacher.achievements.xp',$learner->id) }}" class="rounded-xl border border-green-200 bg-white px-4 py-3 text-xs font-bold">View XP earnings & deductions →</a></div>
        <p class="mt-4 text-sm"><b>Next goal:</b> {{ $report['focus']['name'] ?? 'All milestones earned' }}</p>
        @if($report['awaiting_review'])<p class="mt-2 text-xs text-amber-800">{{ $report['awaiting_review'] }} submitted revisions need teacher review.</p>@endif
        <details class="mt-4 rounded-xl border border-green-100 p-4"><summary class="cursor-pointer text-sm font-bold">View badges and supporting evidence</summary><div class="mt-4 grid gap-3 md:grid-cols-2">
        @foreach($report['cards'] as $card)
            <section class="rounded-xl border {{ $card['earned'] ? 'border-green-300 bg-green-50' : 'border-slate-200 bg-white' }} p-3"><h3 class="text-sm font-bold">{{ $card['name'] }} · {{ $card['earned'] ? 'Earned' : $card['progress'].'%' }}</h3><p class="mt-2 text-xs leading-5">{{ $card['requirement'] }}</p>
            @if($card['earned'])<p class="mt-2 text-xs font-bold">Earned {{ $card['earned_at']->copy()->timezone(config('learning.timezone'))->format('M j, Y g:i A') }} (UTC+08:00)</p><ul class="mt-2 space-y-1">@foreach($card['evidence'] as $item)<li class="text-xs leading-5">{{ $item['title'] }} — {{ $item['detail'] }}</li>@endforeach</ul>@endif</section>
        @endforeach
        </div></details>
    </article>
@empty
    <p class="rounded-2xl border border-dashed border-green-200 bg-white p-10 text-center text-sm">No enrolled students match these filters.</p>
@endforelse
</section>
<div class="mt-6">{{ $students->links() }}</div><div class="mt-6 flex flex-wrap gap-4 text-sm font-bold"><a href="{{ route('teacher.code-lab') }}" class="underline">Review Code Lab work →</a><a href="{{ route('teacher.quiz.reviews') }}" class="underline">Review quiz results →</a></div>
</main></body></html>
