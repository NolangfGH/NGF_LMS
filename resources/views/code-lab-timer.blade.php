@if($labActivity['assignment'])
<section id="labTimer" @if(!$workspace->timer_started_at) hidden @endif class="mb-5 rounded-2xl border border-green-200 bg-white p-5 shadow-card" aria-labelledby="labTimerHeading">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 id="labTimerHeading" class="font-bold text-primary">Assignment time remaining</h2><p class="mt-1 text-xs text-muted"><span id="timerAllotted">{{ $workspace->time_limit_seconds / 60 }}</span> minutes allotted · The earlier of your timer or the due date closes work.</p></div>
        <output id="timerRemaining" class="font-mono text-2xl font-bold text-primary" aria-label="Time remaining">--:--</output>
    </div>
    <div id="timerProgress" class="mt-4 h-3 overflow-hidden rounded-full bg-green-100" role="progressbar" aria-label="Assignment time remaining" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100"><div id="timerBar" class="h-full rounded-full bg-green-700 transition-[width] duration-500" style="width:100%"></div></div>
    <p id="timerHelp" role="status" class="mt-3 text-xs leading-6 text-secondary">Your timer continues across refreshes. Save regularly and submit your code and explanation before time runs out.</p>
</section>
<p id="noLabTimer" @if($workspace->timer_started_at) hidden @endif class="mb-5 rounded-xl border border-green-100 bg-white p-4 text-sm text-secondary">No allotted countdown for this assignment. Follow the teacher's due date and submit your code for review.</p>
@endif
