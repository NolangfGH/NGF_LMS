<form method="POST" action="{{ route('teacher.code-lab.review',$submission) }}" class="space-y-4" data-lab-review data-submission="{{ $submission->id }}" data-ai-url="{{ route('teacher.code-lab.ai',$submission) }}" data-ai-configured="{{ $aiConfigured ? '1' : '0' }}">
    @csrf
    <section class="rounded-xl border border-green-200 bg-green-50 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-bold">AI-assisted rubric draft</h3>
            <button type="button" data-ai-suggest @disabled(!$aiConfigured && !$submission->ai_suggestion) class="rounded-xl border border-green-300 bg-white px-3 py-2 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-50">{{ $submission->ai_suggestion ? 'Use saved AI suggestions' : 'Suggest scores with AI' }}</button>
        </div>
        <p class="mt-3 text-xs leading-6">AI suggests scores and feedback from the submitted code, explanation and assignment context. You can change every value. Only your final save publishes the learning score and feedback to the student.</p>
        <p data-ai-status role="status" aria-live="polite" class="mt-3 text-xs font-semibold">{{ $submission->ai_suggestion ? 'Saved AI draft ready to load.' : ($aiConfigured ? 'AI suggestions will load automatically. You can also score manually.' : 'AI is not configured yet. An administrator can connect OpenAI; manual review remains available.') }}</p>
        <ul data-ai-limitations class="mt-3 space-y-1 text-xs leading-5"></ul>
    </section>
    <h3 class="font-bold">Learning rubric · 16 points</h3>
    <p class="text-xs leading-5">0 = not demonstrated · 1 = beginning · 2 = developing · 3 = mostly secure · 4 = secure. AI reviews the source without compiling it. Check the code and test it in your C++ compiler before finalizing.</p>
    @foreach(['correctness'=>'Meets the objective and handles test cases','logic'=>'Uses suitable steps, conditions and loops','readability'=>'Clear names, indentation and structure','explanation'=>'Explains the solution and testing'] as $criterion=>$description)
    <label class="block">
        <span class="text-sm font-bold">{{ ucfirst($criterion) }}</span>
        <span class="mt-1 block text-xs">{{ $description }}</span>
        <select required name="rubric[{{ $criterion }}]" data-rubric="{{ $criterion }}" class="mt-2 w-full rounded-xl border bg-white p-3 text-sm">
            <option value="">Choose score</option>
            @foreach(range(0,4) as $value)<option value="{{ $value }}">{{ $value }} / 4</option>@endforeach
        </select>
        <span data-ai-rationale="{{ $criterion }}" class="mt-2 block text-xs leading-5 text-green-800"></span>
    </label>
    @endforeach
    <p data-rubric-total class="rounded-xl bg-green-50 p-3 text-sm font-bold">Choose all four scores · maximum 16 points</p>
    <label class="block"><span class="text-sm font-bold">Actionable feedback *</span><textarea name="feedback" required minlength="10" maxlength="5000" rows="5" class="mt-2 w-full rounded-xl border p-3 text-sm" placeholder="One strength, one next step, and a test to try…"></textarea></label>
    <p class="text-xs leading-5">Assignment XP budget: {{ $submission->reward_budget }}. Opening an assignment credits its XP once. Reviews give feedback and a learning score without duplicating that reward. Older work that has not claimed an opening reward keeps its rubric-based XP.</p>
    <button type="submit" class="rounded-xl bg-green-900 px-4 py-3 font-bold text-white">Save feedback & learning score</button>
    <label class="flex items-start gap-3 rounded-xl bg-green-50 p-3 text-sm"><input type="checkbox" name="resubmission_requested" value="1" class="mt-1"><span>Request an improved revision using this feedback. The existing deadline still applies; a closed assignment requires the student's approved final opportunity.</span></label>
    <p class="text-xs text-green-800">Teacher final review: AI suggestions alone never save a final score or send feedback.</p>
</form>
