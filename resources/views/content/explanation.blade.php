<fieldset data-explanation class="rounded-xl border border-green-100 bg-paper p-4">
    <legend class="px-2 font-bold text-primary">Explanation <span data-explanation-number>{{ is_numeric($index) ? $index + 1 : '' }}</span></legend>
    <label>Section heading<input name="sections[{{ $index }}][title]" maxlength="100" required value="{{ $section['title'] ?? '' }}"></label>
    <label class="mt-3">Short explanation<textarea name="sections[{{ $index }}][text]" rows="4" maxlength="5000" required>{{ $section['text'] ?? '' }}</textarea></label>
    <input type="hidden" name="sections[{{ $index }}][media_key]" value="{{ $section['media_key'] ?? '' }}">
    <div class="mt-4 grid gap-4 rounded-xl border border-green-200 bg-white p-4 lg:grid-cols-2">
        <div><label>Explanation image (optional)<input type="file" name="sections[{{ $index }}][image]" accept="image/jpeg,image/png,image/webp" data-explanation-image></label><p class="mt-2 text-xs leading-5">JPEG, PNG or WebP · up to 2 MB.</p>
            <label class="mt-3">Image description<textarea name="sections[{{ $index }}][image_alt]" rows="2" maxlength="240" placeholder="Describe the diagram or image for the learner.">{{ $section['image_alt'] ?? '' }}</textarea></label>
            @if($content && !empty($section['image_path']))<img data-explanation-image-preview src="{{ route('teacher.content.section-image', [$content, $section['media_key']]) }}" alt="{{ $section['image_alt'] ?? 'Current explanation image' }}" class="mt-3 max-h-52 rounded-xl"><label class="mt-3 flex items-center gap-2"><input type="checkbox" name="sections[{{ $index }}][remove_image]" value="1" @checked(old('sections.'.$index.'.remove_image'))>Remove saved image</label>@endif
        </div>
        <div><label>Embed a lesson video (optional)<input type="url" name="sections[{{ $index }}][video_url]" maxlength="2000" value="{{ $section['video_url'] ?? '' }}" placeholder="https://www.youtube.com/watch?v=…"></label><p class="mt-2 text-xs leading-6">Paste a YouTube or Vimeo video link. Choose a video with captions where available. Clear this field to remove the video.</p>
            @if(\App\Services\LessonMedia::embedUrl($section['video_url'] ?? null))<p class="mt-3 text-xs font-bold text-primary">Video attached · students can play it below this explanation.</p>@endif
        </div>
    </div>
    <label class="mt-3">{{ ($designLesson??false) ? 'Worked design example' : ($arduinoLesson ? 'Worked Arduino sketch (.ino)' : 'Worked C++ example') }}<textarea data-explanation-code id="explanation-{{ $index }}-code" name="sections[{{ $index }}][code]" rows="7" maxlength="20000" class="font-mono">{{ $section['code'] ?? '' }}</textarea></label>
    @if($arduinoLesson)@include('wokwi-button', ['wokwiEditor'=>'explanation-'.$index.'-code'])@elseif(!($designLesson??false)) @include('onecompiler-button', ['compilerEditor'=>'explanation-'.$index.'-code'])@endif
    <label class="mt-3">Worked example output<textarea name="sections[{{ $index }}][output]" rows="3" maxlength="5000" class="font-mono">{{ $section['output'] ?? '' }}</textarea></label>
    <button type="button" data-explanation-remove class="mt-4 rounded-xl border bg-white px-3 py-2 text-xs font-bold text-primary">Remove explanation</button>
</fieldset>
