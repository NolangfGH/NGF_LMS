@extends('layouts.teacher-learning', ['pageTitle' => 'System Analytics'])

@section('content')
<section class="rounded-3xl border border-green-200 bg-white p-6 shadow-card sm:p-8">
    <h1 class="text-3xl font-extrabold tracking-tight text-primary">System Analytics</h1>
    <p class="mt-2 max-w-3xl text-sm leading-7">Review a term exam question by question. Compare correct, incorrect and unanswered responses, then inspect which choices students selected.</p>
    <form method="GET" action="{{ route('teacher.analytics') }}" class="mt-6 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
        <label class="block text-sm font-bold text-primary">Classroom
            <select name="classroom" class="mt-2 block w-full rounded-xl border border-green-200 bg-white p-3 font-normal">
                <option value="">All my active sections</option>
                @foreach($classrooms as $classroom)
                    <option value="{{ $classroom->id }}" @selected($selectedRoom?->id === $classroom->id)>{{ $classroom->grade }} · {{ $classroom->section ?: $classroom->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-bold text-primary">School term
            <select name="term" class="mt-2 block w-full rounded-xl border border-green-200 bg-white p-3 font-normal">
                <option value="">All terms</option>
                @foreach([1, 2, 3] as $termNumber)
                    <option value="Term{{ $termNumber }}" @selected(($filters['term'] ?? '') === 'Term'.$termNumber)>Term {{ $termNumber }}</option>
                @endforeach
            </select>
        </label>
        <button class="rounded-xl border border-green-200 bg-paper px-4 py-3 text-sm font-bold text-primary">Find exams</button>
    </form>

    @if($exams->isNotEmpty())
        <form method="GET" action="{{ route('teacher.analytics') }}" class="mt-4 grid gap-3 border-t border-green-100 pt-4 sm:grid-cols-[1fr_auto] sm:items-end">
            @foreach(['term', 'classroom'] as $filter)
                @if(isset($filters[$filter]))<input type="hidden" name="{{ $filter }}" value="{{ $filters[$filter] }}">@endif
            @endforeach
            <label class="block min-w-0 text-sm font-bold text-primary">Published exam or quiz
                <select name="exam" required class="mt-2 block w-full rounded-xl border border-green-200 bg-white p-3 font-normal">
                    <option value="">Choose an assessment</option>
                    @foreach($exams as $option)
                        <option value="{{ $option->id }}" @selected($exam?->id === $option->id)>{{ $option->title }}{{ $option->report_terms ? ' · '.implode(', ', array_map(fn ($term) => str_replace('Term', 'Term ', $term), $option->report_terms)) : '' }} · #{{ $option->id }}</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Analyze questions</button>
        </form>
        <p class="mt-3 text-xs leading-6 text-secondary">Term filters use the linked Grading Sheet term or a term in the assessment title. Choose All terms to find assessments without a term.</p>
    @else
        <p class="mt-5 rounded-xl border border-dashed border-green-200 bg-paper p-4 text-sm">No published assessments match these filters. Try All terms or publish an exam in <a class="font-bold text-primary underline" href="{{ route('teacher.quizzes') }}">Quiz Builder</a>.</p>
    @endif
</section>

@if($report)
    <section class="mt-6 rounded-3xl border border-green-200 bg-white p-6 shadow-card sm:p-8" aria-labelledby="analysis-title">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-bold text-secondary">{{ $selectedRoom ? $selectedRoom->grade.' · '.($selectedRoom->section ?: $selectedRoom->name) : 'All your assigned active sections' }}</p>
                <h2 id="analysis-title" class="mt-2 text-2xl font-extrabold text-primary">{{ $exam->title }}</h2>
                <p class="mt-2 text-xs leading-6">Calculated from the first saved submission per learner. {{ $report['selected_version'] === $report['current_version'] ? 'Current exam version.' : 'Earlier exam version.' }}</p>
            </div>
            @if($report['versions']->count() > 1)
                <form action="{{ route('teacher.analytics') }}" method="GET" class="flex flex-wrap items-end gap-2">
                    @foreach(['term', 'classroom', 'exam'] as $filter)
                        @if(isset($filters[$filter]))<input type="hidden" name="{{ $filter }}" value="{{ $filters[$filter] }}">@endif
                    @endforeach
                    <label class="text-sm font-bold text-primary">Exam version
                        <select name="version" class="mt-2 block rounded-xl border border-green-200 bg-white p-3 text-sm font-normal">
                            @foreach($report['versions'] as $version => $learners)
                                <option value="{{ $version }}" @selected($version === $report['selected_version'])>{{ $version === $report['current_version'] ? 'Current version' : 'Earlier version '.strtoupper(substr($version, 0, 6)) }} · {{ $learners }} learners</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="rounded-xl border border-green-200 bg-paper px-4 py-3 text-sm font-bold text-primary">Show version</button>
                </form>
            @endif
        </div>

        <dl class="mt-5 grid gap-3 sm:grid-cols-3">
            @foreach(['Learners analyzed' => $report['learners'], 'Questions' => $report['question_count'], 'Average score' => $report['average'] === null ? '—' : $report['average'].'%'] as $label => $value)
                <div class="rounded-xl bg-paper p-4"><dt class="text-xs font-bold text-secondary">{{ $label }}</dt><dd class="mt-2 text-2xl font-extrabold text-primary">{{ $value }}</dd></div>
            @endforeach
        </dl>
        @if($report['excluded'])
            <p class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ $report['excluded'] }} {{ Str::plural('submission', $report['excluded']) }} could not be included because the saved question snapshot or complete grading was unavailable.</p>
        @endif
        @if(!$report['learners'])
            <p class="mt-5 rounded-xl border border-dashed border-green-200 p-4 text-sm">No fully graded submissions with a saved question snapshot for this version and section yet. Open this report again after students submit.</p>
        @else
            <details class="mt-5 rounded-xl border border-green-200 bg-paper p-4 text-sm">
                <summary class="cursor-pointer font-bold text-primary">How to read the numbers</summary>
                <div class="mt-3 space-y-2 leading-6">
                    <p><b>Correct %</b> divides correct answers by all responses for the item, including unanswered items. A low percentage can point to a topic to reteach or a question to review.</p>
                    <p><b>Discrimination</b> compares correctness on this item with scores on the rest of the exam. A negative value is a reason to check the wording or answer key. Small groups give less stable estimates. A dash means there are fewer than {{ $report['minimum_correlation_sample'] }} responses, too little score variation, or identical repeated items.</p>
                    <p>Question numbers follow the exam version, not the order seen by a student. Identical repeated questions are grouped. Report statistics leave saved grades unchanged.</p>
                    <a href="https://www.washington.edu/assessment/scanning-scoring/scoring/reports/item-analysis/" class="inline-block font-bold text-primary underline" rel="noopener noreferrer">University of Washington item analysis guide ↗</a>
                </div>
            </details>

            <div class="mt-5 space-y-3">
                @foreach($report['items'] as $item)
                    <details class="rounded-xl border border-green-200 p-4">
                        <summary class="cursor-pointer">
                            <span class="font-bold text-primary">Question {{ implode(', ', $item['numbers']) }}</span>
                            <span class="ml-2 text-sm font-bold">{{ $item['percent_correct'] }}% correct</span>
                            <span class="ml-2 text-xs text-secondary">Discrimination {{ $item['discrimination'] ?? '—' }}</span>
                        </summary>
                        <p class="mt-3 whitespace-pre-wrap break-words text-sm font-semibold text-primary">{{ $item['prompt'] }}</p>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><div class="h-full rounded-full bg-primary" style="width: {{ $item['percent_correct'] }}%"></div></div>
                        <p class="mt-2 text-xs leading-6">{{ $item['correct'] }} correct · {{ $item['wrong'] }} incorrect · {{ $item['blank'] }} unanswered · {{ $item['total'] }} responses</p>
                        @if(in_array($item['type'], ['multiple_choice', 'multiple_answer', 'true_false'], true))
                            <ul class="mt-3 divide-y divide-green-100 rounded-xl bg-paper px-3 text-sm">
                                @foreach($item['options'] as $choiceIndex => $choice)
                                    <li class="flex flex-wrap justify-between gap-2 py-2">
                                        <span class="break-words">{{ $choice }} @if(in_array($choice, $item['expected'], true))<b class="ml-1 text-xs text-primary">(Correct choice)</b>@endif</span>
                                        <span class="shrink-0 font-bold">{{ $item['selections'][$choiceIndex] }} selected · {{ round($item['selections'][$choiceIndex] / $item['total'] * 100, 1) }}%</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if($item['type'] === 'multiple_answer')<p class="mt-2 text-xs">Students can select more than one choice. Choice percentages can add up to more than 100%.</p>@endif
                        @endif
                    </details>
                @endforeach
            </div>
        @endif
    </section>
@endif
@endsection
