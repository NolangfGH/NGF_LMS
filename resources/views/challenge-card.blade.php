<article class="flex flex-col rounded-2xl border {{ $card['progress']['status'] === 'completed' ? 'border-green-300' : 'border-black/10' }} bg-white p-5 shadow-card" data-challenge-card data-source="{{ $card['source'] }}" data-difficulty="{{ $card['difficulty'] }}" data-status="{{ $card['progress']['status'] }}" data-closed="{{ $card['closed'] ? '1' : '0' }}" @if($card['deadline']) data-deadline="{{ $card['deadline']->getTimestampMs() }}" @endif>
    <div class="flex flex-wrap items-center justify-between gap-2"><span class="rounded-full bg-paper px-3 py-1 text-[10px] font-bold uppercase text-secondary">{{ $card['source'] === 'teacher' ? 'Teacher challenge' : 'Guided practice' }}</span><span class="rounded-full bg-green-100 px-3 py-1 text-[10px] font-bold uppercase text-primary">{{ $card['difficulty'] }}</span></div>
    <h2 class="mt-4 text-xl font-extrabold text-primary">{{ $card['title'] }}</h2>
    <p class="mt-2 text-xs font-bold text-secondary">{{ $card['topic'] ?? 'Teacher-selected C++ problem' }}</p>
    <p class="mt-3 text-sm leading-6 text-muted">{{ $card['objective'] }}</p>
    <span data-challenge-label class="mt-4 w-fit rounded-full border border-green-200 px-3 py-1 text-xs font-bold text-primary">{{ $card['closed'] ? 'Closed · '.$card['progress']['label'] : $card['progress']['label'] }}</span>
    @if($card['sample_output'] !== null && $card['sample_output'] !== '')
    <details class="mt-4 rounded-xl bg-paper p-3"><summary class="cursor-pointer text-xs font-bold text-primary">Expected sample output</summary><pre class="mt-3 max-h-52 overflow-auto rounded-xl bg-[#102416] p-4 font-mono text-xs leading-6 text-green-50">{{ $card['sample_output'] }}</pre></details>
    @endif
    <details class="mt-3 rounded-xl border border-green-100 p-3"><summary class="cursor-pointer text-xs font-bold text-primary">Planning hint & test ideas</summary><p class="mt-3 text-xs leading-6 text-muted">{{ $card['hint'] }}</p><p class="mt-2 text-xs leading-6 text-muted">Try a normal case and a boundary case. Explain why your algorithm should work. Review source hints in Code Lab, then test in your C++ compiler.</p></details>
    @if($card['progress']['score'] !== null)
    <div class="mt-4 rounded-xl bg-green-50 p-4"><p class="text-sm font-bold text-primary">Teacher learning score: {{ $card['progress']['score'] }} / 16</p><p class="mt-2 whitespace-pre-wrap text-xs leading-6 text-muted">{{ $card['progress']['feedback'] }}</p></div>
    @endif
    <div class="mt-auto pt-5">
    @if($card['assignment'])
        <p class="text-xs leading-6 text-muted">School Term: {{ $card['assignment']->school_term ?: 'Not set' }}<br>Original due date: {{ $card['assignment']->due_at ? $card['assignment']->due_at->timezone(config('learning.timezone'))->format('M j, Y g:i A').' (UTC+08:00)' : 'No due date' }}</p>
        <p class="mt-2 text-xs font-bold text-secondary">{{ $card['workspace']?->assignment_opened_at ? 'Opening XP already credited' : '+'.$card['assignment']->xp.' XP on first open' }} · Timer or due date may close work.</p>
        <form method="POST" action="{{ route('student.assignments.open',$card['assignment']) }}" class="mt-4">@csrf<button data-challenge-open @disabled($card['closed']) class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-600">{{ $card['closed'] ? 'Challenge closed' : ($card['workspace'] ? 'Continue in Code Lab →' : 'Start in Code Lab →') }}</button></form>
        @include('student-excuse-request',['excuseType'=>'assignment','excuseActivity'=>$card['assignment']])
    @else
        <p class="text-xs leading-6 text-secondary">At your own pace · Teacher feedback and learning badges · No opening XP</p>
        <a href="{{ route('student.code-lab',['activity'=>$card['activity']]) }}" class="mt-4 block rounded-xl bg-primary px-4 py-3 text-center text-sm font-bold text-white">{{ $card['workspace'] ? 'Continue practice →' : 'Start practice →' }}</a>
    @endif
    </div>
</article>
