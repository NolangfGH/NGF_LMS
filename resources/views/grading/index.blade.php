@extends('layouts.teacher-learning')
@section('content')
<link rel="stylesheet" href="{{ asset('css/grading-sheet.css') }}?v=20261101-auto">
<div class="grading-shell" data-grading-sheet data-has-unsaved="{{ old('revision') ? 'true' : 'false' }}" data-status-url="{{ route('teacher.grading.status', $query) }}" data-print="{{ request()->boolean('print') ? 'true' : 'false' }}" data-masked="{{ request()->boolean('mask') ? 'true' : 'false' }}">
    <div class="grading-heading">
        <div><p class="grading-eyebrow">Curriculum Console / Assessment records</p><h1>Grading Sheet</h1><p>Turn saved C++ learning evidence into clear, actionable assessment records.</p>@if($room)<p class="grading-help">{{ $room->grade }} · {{ $room->section ?: $room->name }} · {{ $schoolYear }} · {{ config('grading.terms.'.$term) }}</p>@endif</div>
        <div class="grading-actions grading-controls">
            @if($room)<a href="{{ $sheet ? '#assessment-builder' : '#start-sheet' }}" class="grading-button grading-primary"><span class="material-symbols-outlined">add_circle</span>{{ $sheet ? 'Add assessment' : 'Start grading sheet' }}</a>@endif
            <a href="{{ route('teacher.grading.export', $query) }}" class="grading-button"><span class="material-symbols-outlined">download</span>Export CSV</a>
            <a href="{{ route('teacher.grading', [...$query, 'print' => 1]) }}" class="grading-button" target="_blank" rel="noopener"><span class="material-symbols-outlined">print</span>Print / Save PDF</a>
        </div>
    </div>
    <div class="grading-banner"><span class="material-symbols-outlined">lock</span><p><strong>Private teacher gradebook.</strong> Scores stay within your teaching sections. XP is shown separately from academic grades. Weights are configurable; percentages are not an official school report or a transmuted grade.</p></div>
    @if(session('grading_notice'))<p class="grading-success" role="status">{{ session('grading_notice') }}</p>@endif
    <form method="GET" action="{{ route('teacher.grading') }}" class="grading-card grading-filters grading-controls">
        <label>Grade & section<select name="classroom" required>@forelse($rooms as $option)<option value="{{ $option->id }}" @selected($room?->id === $option->id)>{{ $option->grade }} · {{ $option->section ?: $option->name }} ({{ $option->students_count }})</option>@empty<option value="">No teaching sections</option>@endforelse</select></label>
        <label>School year<input name="school_year" value="{{ $schoolYear }}" pattern="20[0-9]{2}-20[0-9]{2}" maxlength="9" required placeholder="2026-2027"></label>
        <label>School term<select name="term">@foreach(config('grading.terms') as $key => $label)<option value="{{ $key }}" @selected($term === $key)>{{ $label }}</option>@endforeach</select></label>
        <label class="grading-search">Find a coder cadet<input type="search" name="search" value="{{ $query['search'] ?? '' }}" maxlength="100" placeholder="Name, nickname or student ID"></label>
        <button class="grading-button grading-primary">Apply filters</button>
    </form>
    @if(!$room)
        <section class="grading-card grading-empty"><h2>Create your teaching sections first</h2><p>Your grading sheets use your saved class rosters.</p><a href="{{ route('teacher.settings') }}#classes" class="grading-button grading-primary">Open class settings</a></section>
    @else
        <div class="grading-toolbar grading-controls"><p><strong>{{ $room->grade }} · {{ $room->section ?: $room->name }}</strong> · {{ $schoolYear }} · {{ config('grading.terms.'.$term) }}</p><div class="grading-actions"><button type="button" class="grading-button" data-grading-mask aria-pressed="{{ request()->boolean('mask') ? 'true' : 'false' }}">Classroom display: {{ request()->boolean('mask') ? 'aliases' : 'names' }}</button><button type="button" class="grading-button" data-grading-refresh>Refresh data</button></div></div>
        <p class="grading-sync grading-controls" role="status" data-grading-status>Live records refresh every 15 seconds while you are not editing.</p>
        @if(!$sheet)
            <section id="start-sheet" class="grading-card grading-start"><div><h2>Start this term’s grading sheet</h2><p>Use your existing roster, link published activities, and record classroom assessments.</p></div><form method="POST" action="{{ route('teacher.grading.store') }}">@csrf<input type="hidden" name="classroom" value="{{ $room->id }}"><input type="hidden" name="school_year" value="{{ $schoolYear }}"><input type="hidden" name="term" value="{{ $term }}"><button class="grading-button grading-primary">Create grading sheet</button></form></section>
        @endif
        <div id="grading-live">@include('grading.workspace')</div>
    @endif
</div>
<script src="{{ asset('js/grading-sheet.js') }}?v=20261101-auto" defer></script>
@endsection
