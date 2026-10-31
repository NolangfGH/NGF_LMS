<p class="mb-4 text-xs text-secondary"><b>{{ $home['catalog_totals']['lessons'] }}</b> lessons in the current course library. Reading times are estimates; checkpoint practice is untimed.</p>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
@foreach($home['catalog'] as $lesson)
    @php
        $lessonSearchText=mb_strtolower($lesson['title'].' '.$lesson['topic'].' '.$lesson['objective']);
        $lessonMatches=empty($search) || str_contains($lessonSearchText,mb_strtolower($search));
    @endphp
    <article data-home-lesson-card data-search="{{ $lessonSearchText }}" @if(!$lessonMatches) hidden @endif class="flex flex-col rounded-2xl border border-black/5 bg-white p-5 shadow-card"><div class="flex items-center justify-between gap-3"><span class="material-symbols-outlined rounded-xl bg-secondary-soft p-2 text-primary" aria-hidden="true">{{ $lesson['icon'] }}</span><span class="font-mono text-[10px] font-bold uppercase text-secondary">Lesson {{ $loop->iteration }} · {{ $lesson['minutes'] }} min read</span></div><h3 class="mt-4 text-lg font-bold text-primary">{{ $lesson['title'] }}</h3><p class="mt-2 text-xs font-semibold text-secondary">{{ $lesson['topic'] }}</p><p class="mt-3 flex-1 text-sm leading-6 text-muted">{{ $lesson['objective'] }}</p><a href="{{ $lesson['url'] }}" class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-primary underline">{{ $home['student'] ? 'Open lesson' : 'Sign in to open lesson' }} →</a></article>
@endforeach
</div>
