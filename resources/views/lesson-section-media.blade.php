@php($sectionMediaId = $sectionMediaId ?? ($contentResource ?? null)?->id)
@php($imageRoute = $imageRoute ?? 'student.lesson.section-image')
@if($sectionMediaId && !empty($section['image_path']) && !empty($section['media_key']))
    @php($imageSource = route($imageRoute, [$sectionMediaId, $section['media_key']]))
    <figure class="{{ \App\Services\LessonMedia::layout($section, 'image') }}" data-formatted-lesson-image>
        <a href="{{ $imageSource }}" target="_blank" rel="noopener" title="View full-resolution image">
            <img src="{{ $imageSource }}" alt="{{ ($section['image_alt'] ?? '') ?: 'Illustration for '.$section['title'] }}" loading="lazy">
        </a>
        @if(filled($section['image_caption'] ?? null))<figcaption>{{ $section['image_caption'] }}</figcaption>@endif
        <a class="lesson-media-original" href="{{ $imageSource }}" target="_blank" rel="noopener">View full image ↗</a>
    </figure>
@endif
@if($videoSource = \App\Services\LessonMedia::embedUrl($section['video_url'] ?? null, (int) ($section['video_start'] ?? 0)))
    @php($videoTitle = ($section['video_title'] ?? '') ?: 'Video · '.$section['title'])
    <section class="{{ \App\Services\LessonMedia::layout($section, 'video') }}" data-lesson-video>
        <h3 class="lesson-media-heading">{{ $videoTitle }}</h3>
        @if(filled($section['video_caption'] ?? null))<p class="lesson-media-caption">{{ $section['video_caption'] }}</p>@endif
        <div class="lesson-media-placeholder lesson-media-video-placeholder" data-video-placeholder>
            <p>Video loads from {{ str_contains($videoSource, 'youtube') ? 'YouTube' : 'Vimeo' }} when you choose Play.</p>
            <button type="button" data-load-lesson-video data-video-src="{{ $videoSource }}" data-video-title="{{ $videoTitle }}" class="lesson-media-play">▶ Play lesson video</button>
        </div>
        <div data-lesson-video-player hidden></div>
        <noscript><a href="{{ $section['video_url'] }}" target="_blank" rel="noopener noreferrer">Open lesson video ↗</a></noscript>
    </section>
@endif
