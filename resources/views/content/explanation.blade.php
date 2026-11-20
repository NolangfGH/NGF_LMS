<fieldset data-explanation class="rounded-xl border border-green-100 bg-paper p-4">
    <legend class="px-2 font-bold text-primary">Explanation <span data-explanation-number>{{ is_numeric($index) ? $index + 1 : '' }}</span></legend>
    <label>Section heading<input name="sections[{{ $index }}][title]" maxlength="100" required value="{{ $section['title'] ?? '' }}"></label>
    <div data-lesson-formatting class="mt-3">
        <label data-format-fallback>Short explanation<textarea name="sections[{{ $index }}][text]" rows="4" maxlength="5000" required>{{ $section['text'] ?? '' }}</textarea></label>
        <div data-format-enhanced hidden>
            <p class="mb-2 text-sm font-bold text-primary">Short explanation</p>
            <div class="lesson-format-box">
                <div class="lesson-format-toolbar" role="toolbar" aria-label="Explanation text formatting">
                    <select data-format-command="formatBlock" aria-label="Paragraph or heading" title="Paragraph or heading">
                        <option value="">Text style</option><option value="p">Paragraph</option><option value="h3">Heading</option><option value="h4">Subheading</option><option value="blockquote">Quote</option>
                    </select>
                    <button type="button" data-format-command="bold" title="Bold (Ctrl+B)" aria-label="Bold"><strong>B</strong></button>
                    <button type="button" data-format-command="italic" title="Italic (Ctrl+I)" aria-label="Italic"><em>I</em></button>
                    <button type="button" data-format-command="underline" title="Underline (Ctrl+U)" aria-label="Underline"><u>U</u></button>
                    <button type="button" data-format-command="strikeThrough" title="Strikethrough" aria-label="Strikethrough"><s>S</s></button>
                    <button type="button" data-format-command="insertUnorderedList" title="Bulleted list" aria-label="Bulleted list">• List</button>
                    <button type="button" data-format-command="insertOrderedList" title="Numbered list" aria-label="Numbered list">1. List</button>
                    <select data-format-command="justify" aria-label="Text alignment" title="Text alignment"><option value="">Align</option><option value="Left">Left</option><option value="Center">Center</option><option value="Right">Right</option></select>
                    <select data-format-command="foreColor" aria-label="Text color" title="Text color"><option value="">Color</option><option value="#14532d">Green</option><option value="#1e40af">Blue</option><option value="#991b1b">Red</option><option value="#000000">Default</option></select>
                    <button type="button" data-format-command="hiliteColor" data-format-value="#fef08a" title="Highlight selected text" aria-label="Highlight text"><mark>Highlight</mark></button>
                    <button type="button" data-format-command="superscript" title="Superscript" aria-label="Superscript">x²</button>
                    <button type="button" data-format-command="subscript" title="Subscript" aria-label="Subscript">x₂</button>
                    <button type="button" data-format-command="undo" title="Undo" aria-label="Undo">↶</button>
                    <button type="button" data-format-command="redo" title="Redo" aria-label="Redo">↷</button>
                    <button type="button" data-format-command="removeFormat" title="Clear inline formatting" aria-label="Clear inline formatting">Clear</button>
                </div>
                <div data-format-editor class="lesson-rich-text lesson-format-editor" contenteditable="true" role="textbox" aria-label="Short explanation" aria-multiline="true" spellcheck="true">{!! \App\Services\LessonTextFormatting::render($section) !!}</div>
            </div>
            <p class="mt-2 flex flex-wrap justify-between gap-2 text-xs text-secondary"><span>Select text to format it. The lesson displays the same formatting.</span><span data-format-count aria-live="polite"></span></p>
            <input type="hidden" data-format-html name="sections[{{ $index }}][formatted_text]" disabled>
        </div>
    </div>
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
