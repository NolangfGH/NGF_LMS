@extends('layouts.teacher-learning', ['pageTitle' => 'Registration Approvals'])
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div><p class="font-mono text-xs font-bold uppercase tracking-widest text-secondary">Verify before access</p><h1 class="mt-2 text-3xl font-extrabold text-primary">Registration Approvals</h1><p class="mt-3 max-w-2xl text-sm leading-7">Confirm each student belongs to your class before approving. Only requests from your own sections appear here.</p></div>
    <a href="{{ route('teacher.students') }}" class="rounded-xl border bg-white px-4 py-3 text-sm font-bold text-primary">View enrolled students →</a>
</div>
<div class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-5 text-sm leading-7"><b class="text-primary">Pending students cannot enter the learning platform.</b> Approval enables sign-in and awards 100 welcome XP once. Declining keeps access blocked and shares your reason privately with the student. Existing approved accounts keep their access.</div>
<form method="GET" action="{{ route('teacher.registrations') }}" class="mb-5 grid items-end gap-3 rounded-2xl bg-white p-5 sm:grid-cols-2 xl:grid-cols-4">
    <label class="text-xs font-bold text-secondary">Search student<input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Name, nickname or Student ID" class="mt-2 w-full rounded-xl border p-3 text-sm text-primary"></label>
    <label class="text-xs font-bold text-secondary">Grade & section<select name="classroom" class="mt-2 w-full rounded-xl border bg-white p-3 text-sm text-primary"><option value="">All my sections</option>@foreach($classrooms as $room)<option value="{{ $room->id }}" @selected((string) $filters['classroom'] === (string) $room->id)>{{ $room->grade }} · {{ $room->section ?: $room->name }}{{ $room->archived_at ? ' (archived)' : '' }}</option>@endforeach</select></label>
    <label class="text-xs font-bold text-secondary">Review status<select name="status" class="mt-2 w-full rounded-xl border bg-white p-3 text-sm text-primary">@foreach(['pending'=>'Awaiting approval','approved'=>'Approved','rejected'=>'Declined','all'=>'All registrations'] as $value=>$label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></label>
    <div class="flex gap-3"><button class="rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Apply filters</button><a href="{{ route('teacher.registrations') }}" class="rounded-xl border px-4 py-3 text-sm font-bold text-primary">Reset</a></div>
</form>
<div id="registration-queue" data-status-url="{{ route('teacher.registrations.status', request()->query()) }}">@include('registration-queue')</div>
<div class="mt-5 flex flex-wrap items-center gap-3"><button id="registration-refresh" type="button" class="rounded-xl border bg-white px-4 py-3 text-sm font-bold text-primary">Refresh requests ↻</button><p id="registration-live" role="status" aria-live="polite" class="text-xs text-secondary">Updated {{ $updatedAt }} · Refreshes every 20 seconds while no review is being edited.</p></div>
<script src="{{ asset('js/registration-queue.js') }}" defer></script>
@endsection
