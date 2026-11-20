@php($mediaOptions = \App\Services\LessonMedia::OPTIONS)
<div data-media-editor class="mt-4 grid min-w-0 gap-4 lg:grid-cols-2">
    <section class="min-w-0 rounded-xl border border-green-200 bg-white p-4">
        <h3 class="font-bold text-primary">Image studio</h3>
        <p class="mt-1 text-xs leading-5 text-secondary">Upload a diagram or photo, then arrange it for your lesson.</p>
        <label class="mt-3">Explanation image (optional)<input type="file" name="sections[{{ $index }}][image]" accept="image/jpeg,image/png,image/webp" data-explanation-image></label>
        <p class="mt-2 text-xs leading-5">JPEG, PNG or WebP · up to 2 MB.</p>
        <label class="mt-3">Image description (alternative text)<textarea name="sections[{{ $index }}][image_alt]" rows="2" maxlength="240" placeholder="Describe the diagram or image for learners.">{{ $section['image_alt'] ?? '' }}</textarea></label>
        <label class="mt-3">Caption (optional)<input name="sections[{{ $index }}][image_caption]" data-media-field="image_caption" maxlength="500" value="{{ $section['image_caption'] ?? '' }}" placeholder="Figure 1. Explain what the image shows."></label>
        <div class="lesson-media-controls mt-3">
            @foreach(['image_width'=>'Display width','image_alignment'=>'Alignment','image_aspect'=>'Aspect ratio','image_fit'=>'Image fit','image_position'=>'Crop position','image_frame'=>'Frame style'] as $field=>$label)
                <label>{{ $label }}<select name="sections[{{ $index }}][{{ $field }}]" data-media-field="{{ $field }}">@foreach($mediaOptions[$field] as $value=>$caption)<option value="{{ $value }}" @selected(\App\Services\LessonMedia::option($section,$field)===$value)>{{ $caption }}</option>@endforeach</select></label>
            @endforeach
        </div>
        @php($savedImage = $content && !empty($section['image_path']) ? route('teacher.content.section-image', [$content, $section['media_key']]) : '')
        @if($savedImage)<label class="mt-3 flex items-center gap-2"><input type="checkbox" name="sections[{{ $index }}][remove_image]" value="1" data-remove-image @checked(old('sections.'.$index.'.remove_image'))>Remove saved image</label>@endif
        <div class="lesson-media-stage mt-4">
            <p class="lesson-media-stage-label">Image preview</p>
            <figure data-media-preview="image" class="{{ \App\Services\LessonMedia::layout($section,'image') }}">
                <img data-explanation-image-preview data-saved-src="{{ $savedImage }}" @if($savedImage) src="{{ $savedImage }}" @endif alt="{{ ($section['image_alt'] ?? '') ?: 'Image preview' }}" @unless($savedImage) hidden @endunless>
                <div class="lesson-media-placeholder" data-image-placeholder @if($savedImage) hidden @endif>Choose an image to preview its size, crop and frame.</div>
                <figcaption data-media-caption="image" @unless(filled($section['image_caption'] ?? null)) hidden @endunless>{{ $section['image_caption'] ?? '' }}</figcaption>
            </figure>
        </div>
    </section>
    <section class="min-w-0 rounded-xl border border-green-200 bg-white p-4">
        <h3 class="font-bold text-primary">Video studio</h3>
        <p class="mt-1 text-xs leading-5 text-secondary">Control the player's placement, viewing area and start time.</p>
        <label class="mt-3">Embed a lesson video (optional)<input type="url" name="sections[{{ $index }}][video_url]" maxlength="2000" data-media-field="video_url" value="{{ $section['video_url'] ?? '' }}" placeholder="https://www.youtube.com/watch?v=…"></label>
        <p class="mt-2 text-xs leading-6">Use an HTTPS YouTube or Vimeo link. Choose captioned videos where possible. No video loads until Play is selected.</p>
        <label class="mt-3">Video title<input name="sections[{{ $index }}][video_title]" data-media-field="video_title" maxlength="120" value="{{ $section['video_title'] ?? '' }}" placeholder="Title displayed above the player"></label>
        <label class="mt-3">Video caption or viewing instructions<textarea name="sections[{{ $index }}][video_caption]" data-media-field="video_caption" rows="2" maxlength="500" placeholder="What should learners notice while watching?">{{ $section['video_caption'] ?? '' }}</textarea></label>
        <div class="lesson-media-controls mt-3">
            @foreach(['video_width'=>'Display width','video_alignment'=>'Alignment','video_aspect'=>'Aspect ratio','video_frame'=>'Frame style'] as $field=>$label)
                <label>{{ $label }}<select name="sections[{{ $index }}][{{ $field }}]" data-media-field="{{ $field }}">@foreach($mediaOptions[$field] as $value=>$caption)<option value="{{ $value }}" @selected(\App\Services\LessonMedia::option($section,$field)===$value)>{{ $caption }}</option>@endforeach</select></label>
            @endforeach
            <label>Start at (seconds)<input name="sections[{{ $index }}][video_start]" data-media-field="video_start" type="number" min="0" max="14400" step="1" value="{{ $section['video_start'] ?? 0 }}"></label>
        </div>
        <div class="lesson-media-stage mt-4">
            <p class="lesson-media-stage-label">Video layout preview</p>
            <div data-media-preview="video" class="{{ \App\Services\LessonMedia::layout($section,'video') }}" data-lesson-video>
                <p class="lesson-media-heading" data-video-preview-title>{{ ($section['video_title'] ?? '') ?: 'Lesson video' }}</p>
                <div class="lesson-media-placeholder lesson-media-video-placeholder" data-video-placeholder>
                    <p data-video-preview-status>Paste a supported video URL to preview.</p>
                    <button type="button" data-load-lesson-video data-video-src="" data-video-title="Lesson video" class="lesson-media-play" disabled>▶ Play preview</button>
                </div>
                <div data-lesson-video-player hidden></div>
                <p data-media-caption="video" @unless(filled($section['video_caption'] ?? null)) hidden @endunless>{{ $section['video_caption'] ?? '' }}</p>
            </div>
        </div>
    </section>
</div>
