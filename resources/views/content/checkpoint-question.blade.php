<fieldset data-checkpoint-question class="rounded-xl border border-green-100 p-4">
    <legend class="px-2 font-bold text-primary">Checkpoint question <span data-checkpoint-number>{{ is_int($index) ? $index+1 : '' }}</span></legend>
    <div class="mb-3 flex justify-end"><button type="button" data-checkpoint-remove @disabled(count($checkpointChecks) <= \App\Models\LessonContent::MIN_CHECKPOINT_QUESTIONS) class="rounded-lg px-3 py-2 text-xs font-bold text-red-700 disabled:cursor-not-allowed disabled:opacity-40">Remove question</button></div>
    <label>Question<input name="checks[{{ $index }}][prompt]" maxlength="1000" required value="{{ old('checks.'.$index.'.prompt',$check['prompt'] ?? '') }}"></label>
    <div class="mt-3 grid gap-3 sm:grid-cols-3">@for($option=0;$option<3;$option++)<label>Choice {{ $option+1 }}<input name="checks[{{ $index }}][options][{{ $option }}]" maxlength="500" required value="{{ old('checks.'.$index.'.options.'.$option,$check['options'][$option] ?? '') }}"></label>@endfor</div>
    <label class="mt-3">Correct choice<select name="checks[{{ $index }}][correct]" required>@for($option=0;$option<3;$option++)<option value="{{ $option }}" @selected((int)old('checks.'.$index.'.correct',$check['correct'] ?? 0)===$option)>Choice {{ $option+1 }}</option>@endfor</select></label>
    <label class="mt-3">Why this answer is correct<textarea name="checks[{{ $index }}][why]" rows="2" maxlength="1000" required>{{ old('checks.'.$index.'.why',$check['why'] ?? '') }}</textarea></label>
</fieldset>
