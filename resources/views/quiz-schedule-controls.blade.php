<section class="overflow-hidden rounded-2xl border border-green-200 bg-white" aria-labelledby="quizAudienceTitle">
    <div class="border-b border-green-100 bg-green-50 p-5">
        <p class="text-[10px] font-bold uppercase tracking-[.18em] text-secondary">Quiz audience</p>
        <h2 id="quizAudienceTitle" class="mt-1 text-lg font-extrabold text-primary">Choose grade level & sections</h2>
        <p class="mt-2 text-sm leading-6 text-muted">Select a grade, then check the sections that will receive this quiz.</p>
    </div>
    <div class="space-y-5 p-5">
        <label class="block" for="quizGradeFilter"><span class="mb-2 block text-sm font-bold text-primary">Grade level</span>
            <select id="quizGradeFilter" class="w-full rounded-xl border border-green-200 bg-paper px-4 py-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-green-100">
                @forelse($classrooms->pluck('grade')->unique() as $grade)
                    <option value="{{ $grade }}">{{ $grade }}</option>
                @empty
                    <option value="">No grade levels available</option>
                @endforelse
            </select>
        </label>
        <fieldset aria-describedby="sectionSelectionHint">
            <legend class="mb-2 text-sm font-bold text-primary">Sections *</legend>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-3 text-xs font-bold">
                    <button type="button" id="selectAllSections" class="text-primary underline underline-offset-4">Select all sections</button>
                    <button type="button" id="clearGradeSections" class="text-secondary underline underline-offset-4">Clear grade</button>
                </div>
            </div>
            <p id="sectionSelectionHint" class="mb-3 text-xs leading-5 text-muted">Choose one or more sections in this grade. Selections stay saved when you switch grades.</p>
            <div class="grid max-h-80 gap-3 overflow-y-auto p-1 sm:grid-cols-2" id="quizSectionGrid">
                @foreach($classrooms as $room)
                    <label class="quiz-section-card relative cursor-pointer" data-grade="{{ $room->grade }}">
                        <input type="checkbox" name="classroom_ids[]" value="{{ $room->id }}" data-grade="{{ $room->grade }}" data-label="{{ $room->grade }} · {{ $room->section ?: 'Current class' }} · {{ $room->name }}" data-students="{{ $room->students_count }}" class="quiz-section peer absolute right-4 top-4 h-4 w-4 accent-green-800" @checked($loop->first)>
                        <span class="block h-full rounded-xl border border-slate-200 bg-white p-4 pr-10 transition peer-checked:border-green-700 peer-checked:bg-green-50 peer-focus-visible:ring-2 peer-focus-visible:ring-green-700">
                            <span class="block text-sm font-extrabold text-primary">{{ $room->section ?: 'Current class' }}</span>
                            <span class="mt-1 block text-xs leading-5 text-muted">{{ $room->name }}</span>
                            <span class="mt-3 block text-[11px] font-semibold text-secondary">{{ $room->students_count }} students · {{ $room->period }}</span>
                            @unless($room->section)<span class="mt-2 block text-[10px] text-secondary">Section name not set</span>@endunless
                        </span>
                    </label>
                @endforeach
            </div>
            @if($classrooms->isEmpty())<p class="rounded-xl bg-paper p-4 text-sm">Create a classroom before assigning a quiz.</p>@endif
        </fieldset>
        <div class="rounded-xl border border-green-200 bg-paper p-4">
            <p id="sectionCount" class="text-sm font-bold text-primary" aria-live="polite"></p>
            <div id="selectedSectionSummary" class="mt-3 flex flex-wrap gap-2" aria-label="Selected sections"></div>
        </div>
        <a href="#gradeSectionManager" id="manageSectionsLink" class="inline-block text-xs font-bold text-primary underline underline-offset-4">+ Add or name a section</a>
    </div>
</section>
<fieldset class="rounded-2xl border border-green-200 bg-white p-5 space-y-4">
    <legend class="px-2 text-sm font-bold text-primary">Quiz schedule</legend>
    <div class="grid gap-4 sm:grid-cols-2">
        <label><span class="mb-2 block text-sm font-bold text-primary">Start date and time</span><input type="datetime-local" name="opens_at" class="w-full min-w-0 rounded-xl border border-slate-200 bg-white p-3 text-sm"></label>
        <label><span class="mb-2 block text-sm font-bold text-primary">Closing date and time</span><input type="datetime-local" name="closes_at" class="w-full min-w-0 rounded-xl border border-slate-200 bg-white p-3 text-sm"></label>
    </div>
    <p class="text-xs leading-5 text-muted">Asia/Singapore (UTC+08:00). Leave both blank for an anytime quiz. Students see the schedule immediately; questions open at the start time and submissions close at the closing time.</p>
</fieldset>
