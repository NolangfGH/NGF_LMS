<section id="achievements" class="rounded-2xl border border-green-200 bg-white p-6 shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><span class="font-mono text-[10px] font-bold uppercase text-secondary">Your collection</span><h2 class="mt-1 text-xl font-bold text-primary">{{ $achievementReport['earned'] }} / {{ $achievementReport['total'] }} learning badges</h2></div><a href="{{ route('student.achievements') }}" class="text-sm font-bold text-primary underline">Explore achievements →</a></div>
    <p class="mt-4 text-sm leading-6 text-muted">Badges come from saved quizzes, coding activities, and final teacher reviews.</p>
    <div class="mt-4 flex flex-wrap gap-2">@foreach($achievementReport['cards']->where('earned',true)->take(4) as $item)<span class="rounded-full bg-secondary-soft px-3 py-2 text-xs font-bold text-primary">{{ $item['name'] }}</span>@endforeach</div>
</section>
