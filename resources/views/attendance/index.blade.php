@extends('layouts.teacher-learning',['pageTitle'=>'Class Attendance'])
@section('content')
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}?v=1">
<div class="attendance-page" data-attendance data-status-url="{{ route('teacher.attendance.status',['classroom'=>$room?->id,'date'=>$date]) }}">
    <div class="attendance-heading"><div><p class="attendance-eyebrow">NGF_C++CodeQuest · Classroom records</p><h1>Class Attendance</h1><p>Check your class roster and get everyone ready for today’s coding quest.</p></div><a class="attendance-button" href="{{ route('teacher.students') }}">Manage students →</a></div>
    @if(session('attendance_notice'))<p class="attendance-notice" role="status">{{ session('attendance_notice') }}</p>@endif
    <form method="GET" class="attendance-filters attendance-card">
        <label>Grade & section<select name="classroom" required>@forelse($rooms as $class)<option value="{{ $class->id }}" @selected($room?->id===$class->id)>{{ $class->grade }} · {{ $class->section ?: $class->name }}{{ $class->archived_at ? ' · Archived' : '' }}</option>@empty<option value="">No teaching sections</option>@endforelse</select></label>
        <label>Class date<input type="date" name="date" value="{{ $date }}" required></label>
        <button class="attendance-button attendance-primary">Open roster</button>
        <p class="attendance-help">All schedules and check-in times use UTC+08:00.</p>
    </form>
    <div class="attendance-toolbar attendance-no-print"><p data-attendance-message role="status" aria-live="polite">Saved records refresh every 15 seconds while you are not editing.</p><button class="attendance-button" type="button" data-attendance-refresh>Refresh saved records ↻</button></div>
    <div data-attendance-workspace>@include('attendance.workspace')</div>
</div>
<script src="{{ asset('js/attendance.js') }}?v=1" defer></script>
@endsection
