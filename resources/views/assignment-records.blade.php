<div class="mt-5 space-y-4">
@forelse($assignments as $assignment)
    <article class="assignment-record rounded-xl border {{ $editingAssignment?->id === $assignment->id ? 'border-primary ring-2 ring-secondary-soft' : 'border-black/5' }} bg-paper p-4">
        <div class="flex items-start justify-between gap-3">
            <h3 class="min-w-0 break-words text-sm font-bold text-primary">{{ $assignment->title }}</h3>
            <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold {{ $assignment->status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst($assignment->status) }}</span>
        </div>
        <p class="mt-2 text-xs text-muted">{{ $assignment->xp }} XP · {{ $assignment->difficulty }} · {{ $assignment->created_at->format('M j, Y') }}</p>
        <p class="mt-2 text-xs font-bold text-secondary">School Term: {{ $assignment->school_term ?: 'Not set' }}</p>
        <p class="mt-2 text-xs font-bold text-secondary">{{ $assignment->time_limit_minutes ? $assignment->time_limit_minutes.' minute time limit per student' : 'No allotted countdown' }}</p>
        <p class="mt-2 text-xs text-muted">{{ $assignment->due_at ? 'Due '.$assignment->due_at->timezone(config('learning.timezone'))->format('M j, Y · g:i A').' (UTC+08:00)' : 'No due date' }}</p>
        <div class="mt-3 flex flex-wrap gap-1">
            @foreach($assignment->classrooms->isNotEmpty() ? $assignment->classrooms : collect([$assignment->classroom])->filter() as $room)
            <span class="rounded-lg bg-white px-2 py-1 text-xs">{{ $room->grade }} · {{ $room->section ?: $room->name }}</span>
            @endforeach
        </div>
        @if($assignment->sample_output !== null && $assignment->sample_output !== '')
        <details class="mt-3 text-xs"><summary class="cursor-pointer font-bold text-secondary">Assignment Sample Output</summary><pre class="mt-2 max-h-48 overflow-auto rounded-xl bg-primary p-3 font-mono leading-6 text-white">{{ $assignment->sample_output }}</pre></details>
        @endif
        <div class="mt-4 flex flex-wrap gap-2 border-t border-black/5 pt-3">
            <a href="{{ route($builderKind === 'challenge' ? 'teacher.section-challenges' : 'teacher.assignments',['edit'=>$assignment->id]) }}#assignmentForm" class="inline-flex items-center gap-1 rounded-lg bg-primary-container px-3 py-2 text-xs font-bold text-white" aria-label="Edit {{ $assignment->title }}"><span class="material-symbols-outlined text-base" aria-hidden="true">edit</span>Edit</a>
            <button type="button" data-delete-assignment data-delete-url="{{ route('teacher.assignments.destroy',$assignment) }}" data-title="{{ $assignment->title }}" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-700 disabled:opacity-50" aria-label="Delete {{ $assignment->title }}"><span class="material-symbols-outlined text-base" aria-hidden="true">delete</span>Delete</button>
        </div>
    </article>
@empty
    <div class="rounded-xl border border-dashed border-secondary-soft p-5 text-center text-sm text-muted">No assignments saved yet.</div>
@endforelse
</div>
