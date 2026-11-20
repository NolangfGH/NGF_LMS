<form action="{{ route($subjectFilterRoute) }}" method="GET" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-green-100 bg-white p-4">
    <label class="min-w-44 flex-1 text-xs font-bold text-primary">Filter by grade & subject
        <select name="course" class="mt-2 block w-full rounded-xl border border-green-200 bg-white p-3 text-sm font-normal">
            <option value="">All subjects, including unclassified records</option>
            @foreach(\App\Services\LearningCourses::groups() as $grade => $subjects)
                <optgroup label="{{ $grade }}">
                    @foreach($subjects as $key => $label)
                        <option value="{{ $key }}" @selected(($selectedSubject??null)===$key)>{{ $label }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </label>
    <button class="rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Apply filter</button>
    <a class="rounded-xl border px-4 py-3 text-sm font-bold text-primary" href="{{ route($subjectFilterRoute) }}">All subjects</a>
</form>
