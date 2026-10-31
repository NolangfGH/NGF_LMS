<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Excuse Requests | CodeQuest</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="{{ asset('css/app.css') }}">@include('teacher-preferences')@include('brand-head')</head>
<body class="app-admin-standalone bg-[#f6fbee] text-green-950">@include('admin-page-header')<main class="mx-auto max-w-5xl px-5 pb-8 pt-24">
<a href="{{ route('teacher.dashboard') }}" class="text-sm font-bold">← Teacher Dashboard</a>
<div class="my-6 flex flex-wrap items-end justify-between gap-3"><div><h1 class="text-3xl font-extrabold">Excuse & final opportunity requests</h1><p class="mt-3 text-sm">Review the student's reason and evidence. Approval grants one final opportunity for this student only.</p></div><a href="{{ route('teacher.excuses') }}" class="rounded-xl border bg-white p-3 text-sm font-bold">Refresh requests</a></div>
@if(session('excuse_notice'))<p role="status" class="mb-5 rounded-xl bg-green-100 p-4">{{ session('excuse_notice') }}</p>@endif
@if($errors->any())<p role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-red-800">{{ $errors->first() }}</p>@endif
@forelse($requests as $item)
<article class="mb-5 rounded-2xl border border-green-100 bg-white p-6 shadow-sm">
<div class="flex flex-wrap justify-between gap-3"><div><h2 class="text-xl font-bold">{{ $item->activity_title }}</h2><p class="mt-2 text-sm">{{ $item->student->nickname }} · {{ $item->student->student_id }} · {{ $item->student->classroom?->grade }} · {{ $item->student->classroom?->section }}</p><p class="mt-1 text-xs">{{ ucfirst($item->activity_type) }} · Requested {{ $item->created_at->timezone(config('learning.timezone'))->format('M j, Y g:i A') }} (UTC+08:00)</p></div><span class="h-fit rounded-full bg-green-50 px-3 py-2 text-xs font-bold">{{ ucfirst($item->status) }}</span></div>
<p class="mt-4 whitespace-pre-wrap rounded-xl bg-green-50 p-4 text-sm leading-6">{{ $item->reason }}</p>
<a href="{{ route('excuses.proof',$item) }}" target="_blank" rel="noopener" class="mt-4 inline-block text-sm font-bold underline">View private supporting image ↗</a>
@if($item->student->trashed())
<p class="mt-5 rounded-xl bg-amber-50 p-4 text-sm">This student account is deleted. Restore the student from the Students page before reviewing a pending request.</p>
@elseif($item->status === 'pending')
<form method="POST" action="{{ route('teacher.excuses.review',$item) }}" class="mt-5 space-y-4 border-t pt-5">
@csrf
<label class="block text-sm font-bold">Decision<select name="decision" class="mt-2 w-full rounded-xl border bg-white p-3"><option value="approved">Approve final opportunity</option><option value="declined">Decline request</option></select></label>
<label class="block text-sm font-bold">New final deadline (UTC+08:00)<input type="datetime-local" name="expires_at" class="mt-2 w-full rounded-xl border p-3"><span class="mt-2 block text-xs font-normal">Required for approval. The student can start now and must finish before this deadline.</span></label>
@if($item->activity_type === 'assignment')<label class="block text-sm font-bold">Allotted minutes for final opportunity<input type="number" name="duration_minutes" min="1" max="240" value="{{ $item->activity?->time_limit_minutes ?? 30 }}" class="mt-2 w-full rounded-xl border p-3"></label>@else<p class="text-xs">The quiz keeps its random question order and 60 seconds per question. Previous results remain saved; only additional earned XP is credited.</p>@endif
<label class="block text-sm font-bold">Feedback to student *<textarea required minlength="5" maxlength="2000" name="teacher_note" rows="3" class="mt-2 w-full rounded-xl border p-3"></textarea></label>
<button class="rounded-xl bg-green-900 px-4 py-3 text-sm font-bold text-white">Save teacher decision</button>
</form>
@else
<p class="mt-4 whitespace-pre-wrap text-sm"><b>Teacher feedback:</b> {{ $item->teacher_note }}</p>
@if($item->expires_at)<p class="mt-2 text-sm">Final deadline: {{ $item->expires_at->timezone(config('learning.timezone'))->format('M j, Y g:i A') }} (UTC+08:00) · {{ $item->used_at ? 'Final opportunity started' : ($item->isActive() ? 'Waiting for student to start' : 'Approval expired') }}</p>@endif
@endif
</article>
@empty<p class="rounded-2xl bg-white p-8 text-center">No excuse requests from your students yet.</p>@endforelse
{{ $requests->links() }}
</main></body></html>
