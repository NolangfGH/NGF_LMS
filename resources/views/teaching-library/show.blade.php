@extends('layouts.teacher-learning')
@section('content')
@php($arduinoCourse = ($lesson['course'] ?? 'cpp') === 'arduino-uno')
<a href="{{ route('teacher.library', $arduinoCourse ? ['course'=>'arduino-uno'] : []) }}" class="text-sm font-bold text-primary">← Ready-made library</a>
<div class="mt-5 flex flex-wrap items-start justify-between gap-4">
    <div><p class="text-xs font-bold uppercase tracking-widest text-secondary">{{ $lesson['topic'] }} · Review {{ $kind }}</p><h1 class="mt-3 text-3xl font-extrabold text-primary">{{ $activity['title'] }}</h1></div>
    <span class="rounded-full bg-green-100 px-4 py-2 text-xs font-bold text-primary">Teacher preview · Not assigned</span>
</div>
<details class="mt-5 rounded-xl border border-green-100 bg-white p-4 text-sm">
    <summary class="cursor-pointer font-bold text-primary">{{ ($lesson['assessment'] ?? false) ? 'Exam topics' : 'Lesson coverage' }} · {{ $lesson['term'] }}</summary>
    <p class="mt-3 text-xs leading-7">{{ implode(' · ', $lesson['subtopics']) }}</p>
    @empty($lesson['assessment'])
        <a href="{{ route('teacher.content.edit', $lesson['key']) }}" class="mt-3 inline-block text-xs font-bold text-primary underline">Review or edit the lesson →</a>
        <a href="{{ route('teacher.ilaw.edit', $lesson['key']) }}" class="ml-3 mt-3 inline-block text-xs font-bold text-primary underline">Edit ILAW plan →</a>
    @endempty
</details>
<div class="mt-7 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
<div class="min-w-0 space-y-5">
@if($kind === 'quiz')
    <div class="rounded-2xl border border-green-100 bg-white p-6 text-sm leading-7"><h2 class="font-bold text-primary">{{ count($activity['questions']) }} multiple-choice questions</h2><p>{{ $activity['instructions'] }}</p><p class="mt-2 text-xs text-secondary">Earn +10 XP per correct answer, +5 for correct answers within 30 seconds, and +100 XP for a perfect score. The answer key is private to teaching staff.</p></div>
    @foreach($activity['questions'] as $question)
        <article class="rounded-2xl border border-green-100 bg-white p-5 shadow-card">
            <h2 class="whitespace-pre-wrap break-words font-bold text-primary">{{ $loop->iteration }}. {{ $question['prompt'] }}</h2>
            <ol class="mt-4 space-y-2 text-sm">
            @foreach($question['options'] as $option)
                <li class="break-words rounded-xl border px-4 py-3 {{ in_array($option, $question['correct'], true) ? 'border-green-300 bg-green-50' : 'border-gray-100' }}"><span class="font-mono">{{ chr(65 + $loop->index) }}.</span> <code class="whitespace-pre-wrap">{{ $option }}</code>@if(in_array($option, $question['correct'], true))<b class="ml-2 text-primary">✓ Correct</b>@endif</li>
            @endforeach
            </ol>
            @if(!empty($question['explanation']))<p class="mt-4 text-xs leading-6 text-secondary"><b>Why</b> {{ $question['explanation'] }}</p>@endif
        </article>
    @endforeach
@elseif($kind === 'article')
    <article class="rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <div class="flex flex-wrap gap-2 text-xs font-bold text-secondary"><span class="rounded-full bg-paper px-3 py-1">{{ $activity['category'] }}</span><span class="rounded-full bg-green-50 px-3 py-1">Ready to personalize</span></div>
        <h2 class="mt-5 text-lg font-bold text-primary">Article and student reflection prompts</h2>
        <div class="mt-4 whitespace-pre-wrap break-words text-sm leading-8 [overflow-wrap:anywhere]">{{ $activity['body'] }}</div>
    </article>
    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
        <h2 class="font-bold text-amber-950">Teacher discussion guide · private preview only</h2>
        <p class="mt-3 text-sm leading-7 text-amber-950">{{ $activity['teacher_notes'] }}</p>
        <p class="mt-3 text-xs leading-6 text-amber-900">This guide is not copied into the student article. Use it to guide feedback; review each learner’s own explanation before grading.</p>
    </section>
@else
    <article class="rounded-2xl border border-green-100 bg-white p-6 shadow-card">
        <h2 class="text-lg font-bold text-primary">Student task</h2><p class="mt-4 whitespace-pre-line text-sm leading-7">{{ $activity['objective'] }}</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2"><div><h3 class="text-sm font-bold text-primary">Sample input</h3><pre class="mt-2 overflow-x-auto rounded-xl bg-paper p-4 font-mono text-sm">{{ $activity['sample_input'] === '' ? '(No input)' : $activity['sample_input'] }}</pre></div><div><h3 class="text-sm font-bold text-primary">Assignment Sample Output</h3><pre class="mt-2 overflow-x-auto rounded-xl bg-[#102418] p-4 font-mono text-sm text-green-100">{{ $activity['sample_output'] }}</pre></div></div>
        <h3 class="mt-6 font-bold text-primary">Progressive hints</h3><ol class="mt-3 list-inside list-decimal space-y-2 text-sm">@foreach($activity['hints'] as $hint)<li>{{ $hint }}</li>@endforeach</ol>
        <details class="mt-6"><summary class="cursor-pointer text-sm font-bold text-primary">{{ $arduinoCourse ? 'Review starter sketch.ino' : 'Review starter main.cpp' }}</summary><pre class="mt-3 overflow-x-auto rounded-xl bg-[#102418] p-4 text-xs leading-6 text-green-100">{{ $activity['starter_code'] }}</pre></details>
    </article>
    @if($arduinoCourse)<p class="rounded-2xl border border-amber-200 bg-white p-5 text-sm leading-7">Arduino hardware activity: use an inspected circuit with Arduino IDE or a compatible simulator. CodeQuest saves sketches and teacher feedback. Console input/output tests do not evaluate electrical wiring, motion or sensor accuracy.</p>@else<article class="rounded-2xl border border-green-100 bg-white p-6"><h2 class="font-bold text-primary">Input/output test cases</h2><p class="mt-2 text-xs leading-6">The first example is visible to students. Other tests stay hidden. Automatic runs require your configured isolated compiler; the teacher makes the final rubric review. Tests check output, so also review whether the student used the required C++ concept.</p>
        @foreach($activity['test_cases'] as $case)<div class="mt-4 rounded-xl border border-green-100 p-4"><p class="text-xs font-bold text-secondary">Test {{ $loop->iteration }} · {{ $case['hidden'] ? 'Hidden' : 'Visible sample' }}</p><div class="mt-3 grid gap-4 sm:grid-cols-2"><div><b class="text-xs">Input</b><pre class="mt-1 overflow-x-auto whitespace-pre-wrap text-xs">{{ $case['input'] === '' ? '(No input)' : $case['input'] }}</pre></div><div><b class="text-xs">Expected output</b><pre class="mt-1 overflow-x-auto whitespace-pre-wrap text-xs">{{ $case['output'] }}</pre></div></div></div>@endforeach
    </article>@endif
@endif
</div>
<aside class="rounded-2xl border border-green-200 bg-white p-6 shadow-card xl:sticky xl:top-20">
    <h2 class="text-lg font-bold text-primary">Make it your own</h2><p class="mt-3 text-sm leading-6">{{ $kind === 'article' ? 'Create a private article draft. Edit the text, add an image, and adjust grading and response timing in your blog editor before publishing.' : 'Create an editable draft. Choose your sections now; you can change them, the content and schedule before publishing.' }}</p>
    <form action="{{ route('teacher.library.store', [$lesson['key'], $kind]) }}" method="POST" class="mt-5 space-y-5">
        @csrf
        @if($kind === 'article')
        <div class="rounded-xl bg-paper p-4 text-sm leading-7"><b class="text-primary">Your classroom blog</b><p class="mt-2">When published, this article is available to students in all your sections. Each student sees only their own comments and your replies to them.</p></div>
        <p class="text-xs leading-6 text-secondary">Starts as an ungraded discussion with a 5-minute response timer. Both settings can be changed in the blog editor. If grading is enabled, the existing limit of 10 XP per student per article applies.</p>
        @else
        @if($kind !== 'quiz')<label class="block text-sm font-bold text-primary" for="templateTerm">School Term<select id="templateTerm" name="school_term" required class="mt-2 w-full rounded-xl border p-3 font-normal">@foreach(['Term1','Term2','Term3'] as $term)<option @selected(old('school_term', $activity['school_term']) === $term)>{{ $term }}</option>@endforeach</select></label>@endif
        <fieldset><legend class="text-sm font-bold text-primary">Grade & sections</legend><div class="mt-3 max-h-64 space-y-2 overflow-y-auto">
            @forelse($rooms as $room)<label class="flex items-start gap-3 rounded-xl border border-green-100 p-3 text-sm"><input type="checkbox" name="classroom_ids[]" value="{{ $room->id }}" @checked(in_array($room->id, old('classroom_ids', []))) class="mt-1"><span><b class="text-primary">{{ $room->grade }} · {{ $room->section ?: $room->name }}</b><span class="mt-1 block text-xs text-secondary">{{ $room->name }} · {{ $room->students_count }} enrolled</span></span></label>@empty<p class="text-sm">Add an active class first to use a template.</p><a href="{{ route('teacher.classes') }}" class="mt-3 inline-block text-sm font-bold text-primary underline">Create class / section →</a>@endforelse
        </div></fieldset>
        @if($kind !== 'quiz')<p class="rounded-xl bg-paper p-3 text-xs leading-6">Suggested: {{ $activity['difficulty'] }} · {{ $activity['time_limit_minutes'] }} minutes · {{ $activity['xp'] }} XP. All can be edited in the builder.</p>@endif
        @endif
        <button type="submit" @disabled($kind !== 'article' && $rooms->isEmpty()) class="w-full rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white shadow-tactile disabled:opacity-40">Create editable draft →</button>
        <p class="text-xs leading-6 text-secondary">Your copy is saved privately. No student notifications are sent until you publish. Existing work and the original template stay unchanged.</p>
    </form>
</aside>
</div>
@endsection
