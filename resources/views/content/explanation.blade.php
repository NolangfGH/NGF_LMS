<fieldset data-explanation class="rounded-xl border border-green-100 bg-paper p-4">
    <legend class="px-2 font-bold text-primary">Explanation <span data-explanation-number>{{ is_numeric($index) ? $index + 1 : '' }}</span></legend>
    <label>Section heading<input name="sections[{{ $index }}][title]" maxlength="100" required value="{{ $section['title'] ?? '' }}"></label>
    <label class="mt-3">Short explanation<textarea name="sections[{{ $index }}][text]" rows="4" maxlength="5000" required>{{ $section['text'] ?? '' }}</textarea></label>
    <label class="mt-3">{{ $arduinoLesson ? 'Worked Arduino sketch (.ino)' : 'Worked C++ example' }}<textarea data-explanation-code id="explanation-{{ $index }}-code" name="sections[{{ $index }}][code]" rows="7" maxlength="20000" class="font-mono">{{ $section['code'] ?? '' }}</textarea></label>
    @unless($arduinoLesson)@include('onecompiler-button', ['compilerEditor'=>'explanation-'.$index.'-code'])@else<p class="mt-3 text-xs">Verify Arduino examples in Arduino IDE or a compatible Uno simulator. OneCompiler C++ does not simulate hardware.</p>@endunless
    <label class="mt-3">Worked example output<textarea name="sections[{{ $index }}][output]" rows="3" maxlength="5000" class="font-mono">{{ $section['output'] ?? '' }}</textarea></label>
    <button type="button" data-explanation-remove class="mt-4 rounded-xl border bg-white px-3 py-2 text-xs font-bold text-primary">Remove explanation</button>
</fieldset>
