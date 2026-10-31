<article class="flex h-full flex-col rounded-2xl border {{ $badge['earned'] ? 'border-green-300 bg-white' : 'border-black/10 bg-white' }} p-5 shadow-card" data-achievement-card data-earned="{{ $badge['earned'] ? '1' : '0' }}" data-category="{{ $badge['category'] }}">
    <div class="flex items-start justify-between gap-3">
        <span class="grid h-14 w-14 place-items-center rounded-2xl {{ $badge['earned'] ? 'bg-secondary-soft text-primary' : 'bg-paper text-secondary' }}"><span class="material-symbols-outlined text-3xl" aria-hidden="true">{{ $badge['icon'] }}</span></span>
        <span class="rounded-full px-3 py-1 text-[10px] font-bold uppercase {{ $badge['earned'] ? 'bg-green-100 text-green-900' : 'bg-slate-100 text-slate-600' }}">{{ $badge['earned'] ? 'Earned' : ($badge['current'] ? 'In progress' : 'Next to unlock') }}</span>
    </div>
    <p class="mt-4 font-mono text-[10px] font-bold uppercase tracking-wider text-secondary">{{ $badge['category'] }}</p>
    <h3 class="mt-1 text-lg font-extrabold text-primary">{{ $badge['name'] }}</h3>
    <p class="mt-2 text-sm leading-6 text-muted">{{ $badge['requirement'] }}</p>
    <div class="mt-auto pt-4">
        <div class="mb-2 flex justify-between gap-3 text-xs font-semibold text-secondary"><span>{{ min($badge['target'],$badge['current']) }} / {{ $badge['target'] }} {{ $badge['unit'] }}</span><span>{{ $badge['progress'] }}%</span></div>
        <div class="h-2 overflow-hidden rounded-full bg-green-100" role="progressbar" aria-label="{{ $badge['name'] }} progress" aria-valuemin="0" aria-valuemax="{{ $badge['target'] }}" aria-valuenow="{{ min($badge['target'],$badge['current']) }}"><div class="h-full rounded-full bg-secondary" style="width:{{ $badge['progress'] }}%"></div></div>
        @if($badge['earned'])
            <p class="mt-3 text-xs font-bold text-secondary">Earned {{ $badge['earned_at']->copy()->timezone(config('learning.timezone'))->format('M j, Y · g:i A') }} (UTC+08:00)</p>
            <details class="mt-4 rounded-xl bg-paper p-3">
                <summary class="cursor-pointer text-xs font-bold text-primary">View learning evidence</summary>
                <ul class="mt-3 space-y-3">
                @foreach($badge['evidence'] as $evidence)
                    <li class="text-xs leading-5"><b class="block text-primary">{{ $evidence['title'] }}</b><span class="block text-muted">{{ $evidence['detail'] }}</span><time class="text-secondary">{{ \Carbon\Carbon::parse($evidence['at'])->timezone(config('learning.timezone'))->format('M j, Y g:i A') }} (UTC+08:00)</time></li>
                @endforeach
                </ul>
            </details>
        @else
            <p class="mt-3 text-xs leading-5 text-muted">{{ $badge['next_step'] }}</p>
            <form method="POST" action="{{ route('student.achievements.goal') }}" class="mt-4">
                @csrf<input type="hidden" name="badge_code" value="{{ $badge['code'] }}">
                <button class="w-full rounded-xl border border-green-200 bg-paper px-3 py-2 text-xs font-bold text-primary">{{ $achievementReport['focus']['code'] === $badge['code'] ? 'Keep as my focus' : 'Make this my focus' }}</button>
            </form>
        @endif
    </div>
</article>
