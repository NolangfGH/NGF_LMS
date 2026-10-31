@extends('layouts.student', ['pageTitle' => 'Assignments', 'active' => 'assignments'])
@section('content')
@if(session('excuse_notice'))<p role="status" class="mb-5 rounded-xl bg-green-100 p-4">{{ session('excuse_notice') }}</p>@endif
<div class="mb-7">
    <span class="font-mono text-[10px] font-bold uppercase tracking-widest text-secondary">{{ $classroom->name }}</span>
    <h1 class="mt-2 text-3xl font-extrabold text-primary">My Assignments</h1>
    <p class="mt-2 text-sm text-muted">Assignments published by your teacher for {{ $classroom->grade }} · {{ $classroom->period }}.</p>
    <div class="mt-4 rounded-xl border border-green-200 bg-white p-4 text-sm leading-6 text-secondary">Open an assignment before its deadline to receive its XP once. Your total updates immediately in your profile and leaderboard. Continue working and send your solution to your teacher for feedback.</div>
</div>
@if($errors->any())
<div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
@endif
<section class="space-y-4">
@forelse ($publishedAssignments as $assignment)
    @php
        $savedWorkspace=$assignmentWorkspaces->get($assignment->id);
        $personalDeadline=$savedWorkspace ? app(\App\Services\CodeLabTimer::class)->deadline($savedWorkspace) : null;
        $accessDeadline=$personalDeadline ?? $assignment->due_at;
        $closed=$assignment->isClosed() || ($personalDeadline && now()->greaterThanOrEqualTo($personalDeadline));
        $grant=$secondChanceRequests->get('assignment:'.$assignment->id);
        if ($grant?->status === 'approved') {
            $personalDeadline=$savedWorkspace?->second_chance_request_id === $grant->id ? $personalDeadline : null;
            $accessDeadline=$personalDeadline ?? $grant->expires_at;
            $closed=!$grant->isActive() || ($personalDeadline && now()->gte($personalDeadline));
        }
        $rewardReceived=$savedWorkspace?->assignment_opened_at !== null;
        $remainingXp=max(0,$assignment->xp - ($savedWorkspace?->xp_earned ?? 0));
    @endphp
    <article id="assignment-{{ $assignment->id }}" class="scroll-mt-24 rounded-2xl bg-white p-6 shadow-card" data-assignment-card @if($accessDeadline) data-deadline="{{ $accessDeadline->getTimestampMs() }}" @endif>
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
            <div>
                <span data-access-status class="rounded-full bg-secondary-soft px-3 py-1 font-mono text-[10px] font-bold uppercase text-primary">{{ $closed ? 'Closed' : 'Available' }} · {{ $assignment->difficulty }}</span>
                <h2 class="mt-3 text-xl font-bold text-primary">{{ $assignment->title }}</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">{{ $assignment->objective ?: 'Complete this assignment in Code Lab.' }}</p>
            </div>
            <div class="flex shrink-0 flex-col items-start gap-2 md:items-end">
                <span class="font-mono text-sm font-bold text-secondary">{{ $rewardReceived ? number_format($savedWorkspace->xp_earned).' XP credited' : '+'.number_format($remainingXp).' XP on first open' }}</span>
                <span class="text-xs text-muted">{{ $assignment->due_at ? 'Due '.$assignment->due_at->timezone(config('learning.timezone'))->format('M j, Y · g:i A').' (UTC+08:00)' : 'No due date' }}</span>
                @if($grant?->status === 'approved' || $assignment->time_limit_minutes)<span class="text-xs font-bold text-secondary">{{ $grant?->status === 'approved' ? $grant->duration_minutes : ($savedWorkspace?->time_limit_seconds ? $savedWorkspace->time_limit_seconds / 60 : $assignment->time_limit_minutes) }} minutes from opening Code Lab</span>@endif
                <span data-deadline-message class="text-xs font-semibold text-muted">{{ $closed ? 'Work time ended. Code Lab is closed.' : 'XP can only be claimed once per assignment.' }}</span>
            </div>
        </div>
        @if($assignment->sample_output !== null && $assignment->sample_output !== '')
        <details class="mt-4 rounded-xl border border-green-100 bg-paper p-4">
            <summary class="cursor-pointer text-sm font-bold text-primary">Assignment Sample Output</summary>
            <pre class="mt-3 max-h-64 overflow-auto rounded-xl bg-[#102416] p-4 font-mono text-sm leading-7 text-green-50">{{ $assignment->sample_output }}</pre>
        </details>
        @endif
        <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-black/5 pt-4">
            <span class="rounded-lg bg-paper px-3 py-2 text-xs font-bold text-muted">School Term: {{ $assignment->school_term ?: 'Not set' }}</span>
            <span class="rounded-lg bg-paper px-3 py-2 text-xs font-bold text-muted">{{ $assignment->csta_standard ?: 'Teacher assignment' }}</span>
            <form method="POST" action="{{ route('student.assignments.open',$assignment) }}" class="ml-auto" data-assignment-open>
                @csrf
                <button type="submit" data-open-button @disabled($closed) class="rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-tactile disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-600 disabled:shadow-none">{{ $closed ? 'Code Lab closed' : ($rewardReceived ? 'Continue Code Lab →' : 'Open Code Lab →') }}</button>
            </form>
        </div>
        @include('student-excuse-request',['excuseType'=>'assignment','excuseActivity'=>$assignment])
    </article>
@empty
    <section class="rounded-2xl border-2 border-dashed border-secondary-soft bg-white p-10 text-center shadow-card"><span class="material-symbols-outlined text-5xl text-secondary">assignment</span><h2 class="mt-3 text-xl font-bold text-primary">No published assignments yet</h2><p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-muted">Your teacher has not published an assignment for this classroom. Published work will appear here automatically.</p></section>
@endforelse
</section>
<script>
(() => {
    const serverTime = {{ $assignmentServerTime }};
    const started = performance.now();
    function refreshDeadlines() {
        const now = serverTime + performance.now() - started;
        document.querySelectorAll('[data-assignment-card][data-deadline]').forEach(card => {
            if (now < Number(card.dataset.deadline)) return;
            const button = card.querySelector('[data-open-button]');
            button.disabled = true;
            button.textContent = 'Code Lab closed';
            card.querySelector('[data-access-status]').textContent = 'Closed';
            card.querySelector('[data-deadline-message]').textContent = 'Work time ended. Code Lab is closed.';
        });
    }
    refreshDeadlines();
    setInterval(refreshDeadlines, 1000);
    document.addEventListener('visibilitychange', refreshDeadlines);
    document.querySelectorAll('[data-assignment-open]').forEach(form => {
        form.addEventListener('submit', event => {
            refreshDeadlines();
            const button = form.querySelector('[data-open-button]');
            if (button.disabled) { event.preventDefault(); return; }
            button.disabled = true;
            button.textContent = 'Opening…';
        });
    });
    window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
})();
</script>
@include('excuse-status-refresh')
@endsection
