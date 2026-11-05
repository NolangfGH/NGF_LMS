@extends('layouts.teacher-learning')

@section('content')
<div class="mb-7 flex flex-wrap items-start justify-between gap-4">
    <div>
        <a href="{{ route('teacher.library') }}" class="text-sm font-bold text-primary">← Teaching Library</a>
        <p class="mt-5 text-xs font-bold uppercase tracking-widest text-secondary">{{ isset($lesson['term']) ? $lesson['term'].' · ' : '' }}{{ $lesson['topic'] }}</p>
        <h1 class="mt-2 text-3xl font-extrabold text-primary">ILAW lesson plan</h1>
        <p class="mt-2 text-sm">{{ $teacher->fullName() }} · {{ $teacher->school }}</p>
    </div>
    <a href="{{ route('teacher.ilaw.download', $key) }}" class="rounded-xl border border-green-200 bg-white px-4 py-3 text-sm font-bold text-primary">Download {{ $plan ? 'saved plan' : 'worksheet' }} (.html)</a>
</div>
<div class="mb-6 rounded-2xl border border-green-200 bg-white p-5 text-sm leading-7">
    <p class="font-bold text-primary">{{ match ($plan?->status) { 'submitted' => 'Awaiting principal review', 'evaluated' => 'Evaluated by your principal', default => 'Private draft' } }}</p>
    <p>Write the instructional decisions for your class. Use the topic materials as references and submit when the four ILAW sections are ready.</p>
    <a href="{{ \App\Services\IlawPlanTemplate::ORDER_URL }}" class="font-bold text-primary underline" target="_blank" rel="noopener">DepEd Order No. 016, s. 2026 · June 4, 2026</a>
    @if($plan?->evaluated_at)
        <div class="mt-3 rounded-xl bg-paper p-4">
            <p class="font-bold text-primary">{{ $plan->status === 'evaluated' ? 'Principal review' : 'Previous principal review' }} · {{ \Illuminate\Support\Carbon::parse($plan->evaluated_at)->timezone(config('learning.timezone'))->format('M j, Y g:i A') }}</p>
            @if($plan->review_comment)<p class="mt-2 whitespace-pre-wrap break-words">{{ $plan->review_comment }}</p>@endif
        </div>
    @endif
</div>
<form method="POST" action="{{ route('teacher.ilaw.save', $key) }}" class="space-y-5">
    @csrf
    @method('PUT')
    <input type="hidden" name="revision" value="{{ old('revision', $plan?->revision ?? 0) }}">
    @foreach(\App\Services\IlawPlanTemplate::GROUPS as $heading => $fields)
        <fieldset class="space-y-4 rounded-2xl border border-green-100 bg-white p-5 sm:p-6">
            <legend class="px-2 text-lg font-extrabold text-primary">{{ $heading }}</legend>
            @foreach($fields as $field => [$label, $hint])
                <div>
                    <label for="ilaw-{{ $field }}" class="block text-sm font-bold text-primary">{{ $label }}</label>
                    <p id="ilaw-hint-{{ $field }}" class="mt-1 text-xs leading-5 text-secondary">{{ $hint }}</p>
                    @if(in_array($field, ['title', 'learning_area', 'grade_section', 'sessions'], true))
                        <input id="ilaw-{{ $field }}" name="{{ $field }}" @if($field === 'sessions') type="number" min="1" max="100" @else type="text" maxlength="200" @endif value="{{ old($field, $content[$field] ?? '') }}" aria-describedby="ilaw-hint-{{ $field }}" class="mt-2 w-full rounded-xl border border-green-200 px-3 py-2.5 text-sm">
                    @else
                        <textarea id="ilaw-{{ $field }}" name="{{ $field }}" rows="3" maxlength="6000" aria-describedby="ilaw-hint-{{ $field }}" class="mt-2 w-full rounded-xl border border-green-200 px-3 py-2.5 text-sm">{{ old($field, $content[$field] ?? '') }}</textarea>
                    @endif
                </div>
            @endforeach
        </fieldset>
    @endforeach
    <div class="rounded-2xl border border-green-100 bg-white p-5">
        <p class="mb-4 text-sm leading-6">Saving as a draft keeps this version private. Submitting sends the saved version to your school’s principal. Changes require a new review.</p>
        <div class="flex flex-wrap gap-3">
            <button name="action" value="draft" class="rounded-xl border border-green-200 px-5 py-3 text-sm font-bold text-primary">Save private draft</button>
            <button name="action" value="submit" class="rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white">Submit for principal review</button>
        </div>
    </div>
</form>
@endsection
