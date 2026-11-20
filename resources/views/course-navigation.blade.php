<nav aria-label="{{ $courseNavigationLabel ?? 'Learning course' }}" class="mb-6 flex flex-wrap gap-3 rounded-2xl border border-green-100 bg-white p-3">
    @foreach(\App\Services\LearningCourses::TECHNOLOGY as $navigationCourse => $navigationLabel)
        <a href="{{ route($courseNavigationRoute, ($courseNavigationParameters ?? []) + ($navigationCourse === 'cpp' && !($includeCppQuery??false) ? [] : ['course' => $navigationCourse])) }}"
           @if($selectedCourse === $navigationCourse) aria-current="page" @endif
           class="inline-flex items-center rounded-xl border px-4 py-3 text-sm font-bold text-black {{ $selectedCourse === $navigationCourse ? 'border-primary bg-secondary-soft shadow-[inset_3px_0_0_#0b4e00]' : 'border-green-200 bg-white hover:bg-paper' }}">
            {{ $navigationLabel }}
        </a>
    @endforeach
    <form action="{{ route($courseNavigationRoute, $courseNavigationParameters ?? []) }}" method="GET" class="flex min-w-0 flex-1 items-end gap-2">
        <label class="min-w-0 flex-1 text-xs font-bold text-primary">Grade & academic subject
            <select name="course" class="mt-1 block w-full rounded-xl border border-green-200 bg-paper p-2.5 text-sm font-semibold" aria-label="Choose grade and subject">
                @foreach(\App\Services\LearningCourses::groups() as $gradeLabel => $subjects)
                    <optgroup label="{{ $gradeLabel }}">
                        @foreach($subjects as $navigationCourse => $navigationLabel)
                            <option value="{{ $navigationCourse }}" @selected($selectedCourse === $navigationCourse)>{{ $navigationLabel }}{{ \App\Services\LearningCourses::isElective($navigationCourse) ? ' · elective' : '' }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </label>
        <button class="rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white">Open subject</button>
    </form>
    @if(\App\Services\LearningCourses::isAcademic($selectedCourse ?? 'cpp'))
        <span aria-current="page" class="basis-full rounded-xl bg-secondary-soft px-4 py-2 text-sm font-bold text-primary">{{ \App\Services\LearningCourses::grade($selectedCourse) }} · {{ \App\Services\LearningCourses::title($selectedCourse) }}{{ \App\Services\LearningCourses::isElective($selectedCourse) ? ' · RSHS elective' : '' }}</span>
    @endif
</nav>
