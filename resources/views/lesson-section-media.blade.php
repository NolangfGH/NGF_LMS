@if($contentResource && !empty($section['image_path']) && !empty($section['media_key']))
    <figure class="mt-4 rounded-xl border border-green-100 bg-paper p-3"><img src="{{ route('student.lesson.section-image', [$contentResource, $section['media_key']]) }}" alt="{{ ($section['image_alt'] ?? '') ?: 'Illustration for '.$section['title'] }}" loading="lazy" class="mx-auto max-h-96 max-w-full rounded-lg"></figure>
@endif
@if($videoSource = \App\Services\LessonMedia::embedUrl($section['video_url'] ?? null))
    <section class="mt-4 rounded-xl border border-green-200 bg-paper p-4" data-lesson-video>
        <h3 class="text-sm font-bold text-primary">Video · {{ $section['title'] }}</h3><p class="mt-2 text-xs leading-6">Play this video in the lesson. The video loads from {{ str_contains($videoSource, 'youtube') ? 'YouTube' : 'Vimeo' }} when you choose Play.</p>
        <button type="button" data-load-lesson-video data-video-src="{{ $videoSource }}" data-video-title="{{ $section['title'] }}" class="mt-3 rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">▶ Play lesson video</button>
        <div data-lesson-video-player class="mt-3" hidden></div>
        <noscript><a href="{{ $section['video_url'] }}" target="_blank" rel="noopener noreferrer">Open lesson video ↗</a></noscript>
    </section>
@endif
