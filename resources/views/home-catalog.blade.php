@foreach($home['courses'] as $courseKey => $course)
    @php
        $lessons = collect($home['catalog'])->where('course', $courseKey);
        $needle = mb_strtolower($search ?? '');
        $shown = $lessons->filter(fn (array $lesson): bool => str_contains($lesson['search'], $needle))->count();
    @endphp
    <section id="topics-{{ $courseKey }}" class="home-anchor mb-9 last:mb-0" aria-labelledby="topics-title-{{ $courseKey }}" data-home-course @if(!$shown) hidden @endif>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-xs font-bold text-secondary">{{ $course['grade'] }} · {{ $course['title'] }}</p>
                <h3 id="topics-title-{{ $courseKey }}" class="mt-1 text-xl font-extrabold text-primary">{{ $course['heading'] }}</h3>
            </div>
            <span data-home-course-count class="rounded-full bg-secondary-soft px-3 py-1.5 text-xs font-bold text-primary">{{ $shown }} of {{ $course['count'] }} lessons</span>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($lessons as $lesson)
                <article data-home-lesson-card data-search="{{ $lesson['search'] }}" @if(!str_contains($lesson['search'], $needle)) hidden @endif class="flex flex-col rounded-2xl border border-black/5 bg-white p-5 shadow-card">
                    <div class="flex items-center justify-between gap-3">
                        <span class="material-symbols-outlined rounded-xl bg-secondary-soft p-2 text-primary" aria-hidden="true">{{ $lesson['icon'] }}</span>
                        <span class="font-mono text-[10px] font-bold uppercase text-secondary">Lesson {{ $lesson['number'] }} · {{ $lesson['minutes'] }} min read</span>
                    </div>
                    <h4 class="mt-4 text-lg font-bold text-primary">{{ $lesson['title'] }}</h4>
                    @if($courseKey === 'cpp')<p class="mt-2 text-xs font-semibold text-secondary">{{ $lesson['topic'] }}</p>@endif
                    <p class="mt-3 flex-1 text-sm leading-6 text-muted">{{ $lesson['objective'] }}</p>
                    <a href="{{ $lesson['url'] }}" aria-label="{{ $home['student'] ? 'Open lesson' : 'Sign in to open lesson' }}: {{ $lesson['title'] }}" class="mt-5 text-sm font-bold text-primary underline">{{ $home['student'] ? 'Open lesson' : 'Sign in to open lesson' }} →</a>
                </article>
            @endforeach
        </div>
    </section>
@endforeach
