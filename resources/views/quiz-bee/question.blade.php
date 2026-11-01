@php($fillBlank = ($question['type'] ?? 'multiple_choice') === 'fill_blank')
<details data-bee-question class="bee-question" @if(!is_int($index))open @endif>
    <summary><strong>{{ $kind==='regular' ? 'Question' : 'Tie-breaker' }} <span data-bee-question-number>{{ is_int($index) ? $index+1 : '' }}</span></strong> · {{ mb_strimwidth($question['prompt'] ?? '',0,80,'…') ?: 'New question' }}</summary>
    <div class="bee-form">
        @if($level === 'difficult')
            <label>Question type<select data-bee-question-type name="questions[{{ $level }}][{{ $kind }}][{{ $index }}][type]">
                <option value="multiple_choice" @selected(!$fillBlank)>Multiple choice</option>
                <option value="fill_blank" @selected($fillBlank)>Fill in the blank</option>
            </select></label>
        @endif
        <label>Question<input name="questions[{{ $level }}][{{ $kind }}][{{ $index }}][prompt]" value="{{ $question['prompt'] ?? '' }}" required maxlength="1000"></label>
        <div data-bee-choice-fields @if($fillBlank)hidden @endif>
            <div class="bee-field-grid">
                @for($choice=0;$choice<4;$choice++)
                    <label>Choice {{ $choice+1 }}<input name="questions[{{ $level }}][{{ $kind }}][{{ $index }}][options][{{ $choice }}]" value="{{ $question['options'][$choice] ?? '' }}" required @disabled($fillBlank) maxlength="500"></label>
                @endfor
            </div>
            <label>Correct answer<select name="questions[{{ $level }}][{{ $kind }}][{{ $index }}][correct]" required @disabled($fillBlank)>
                @for($choice=0;$choice<4;$choice++)<option value="{{ $choice }}" @selected((int)($question['correct'] ?? 0)===$choice)>Choice {{ $choice+1 }}</option>@endfor
            </select></label>
        </div>
        @if($level === 'difficult')
            <div data-bee-blank-fields @if(!$fillBlank)hidden @endif>
                <label>Accepted answer(s)<textarea name="questions[{{ $level }}][{{ $kind }}][{{ $index }}][accepted_answers]" rows="3" required @disabled(!$fillBlank) placeholder="Enter one accepted answer per line">{{ implode("\n", (array)($question['accepted_answers'] ?? [])) }}</textarea></label>
                <p class="bee-help">Use _____ to show one blank. Enter up to 10 accepted answers, one per line (maximum 500 characters each). Leading and trailing spaces are ignored; all other spacing and punctuation must match.</p>
                <label>Capitalization<select name="questions[{{ $level }}][{{ $kind }}][{{ $index }}][case_sensitive]" @disabled(!$fillBlank)>
                    <option value="1" @selected($question['case_sensitive'] ?? true)>Match exactly · recommended for C++ code</option>
                    <option value="0" @selected(!($question['case_sensitive'] ?? true))>Ignore capitalization · suitable for vocabulary</option>
                </select></label>
            </div>
        @endif
        <label>Explanation shown after the question closes<textarea name="questions[{{ $level }}][{{ $kind }}][{{ $index }}][why]" rows="2" maxlength="1000">{{ $question['why'] ?? '' }}</textarea></label>
        @if($kind==='regular')<button type="button" class="bee-button" data-bee-remove-question>Remove regular question</button>@endif
    </div>
</details>
