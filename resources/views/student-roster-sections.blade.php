<section aria-label="Teaching sections" class="flex flex-wrap items-start gap-3">
<a href="{{ route('teacher.students',[...request()->except(['classroom','page']),'classroom'=>'']) }}" class="rounded-xl border border-green-200 px-4 py-3 text-sm font-bold {{ !$filters['classroom'] ? 'bg-primary text-white' : 'bg-white text-primary' }}">All sections · {{ $metrics['active'] }} active</a>
@foreach($classrooms as $room)
<article class="min-w-48 rounded-2xl border border-green-200 bg-white p-3">
<a href="{{ route('teacher.students',[...request()->except('page'),'classroom'=>$room->id]) }}" class="block rounded-xl px-3 py-2 text-sm font-bold {{ (int)$filters['classroom']===$room->id ? 'bg-primary text-white' : 'bg-paper text-primary' }}">{{ $room->grade }} · {{ $room->section ?: $room->name }} <span class="ml-1 opacity-80">{{ $room->students_count }} active</span></a>
<label for="roster-class-code-{{ $room->id }}" class="mb-2 mt-3 block text-[10px] font-bold uppercase tracking-widest text-secondary">Class code</label>
<div class="flex items-center gap-2"><input id="roster-class-code-{{ $room->id }}" type="text" readonly value="{{ $room->join_code }}" aria-label="Class code for {{ $room->grade }} {{ $room->section ?: $room->name }}" class="w-36 min-w-0 rounded-lg border border-green-100 bg-paper px-3 py-2 font-mono text-sm font-bold tracking-wider text-primary" spellcheck="false"><button type="button" data-copy-class-code="roster-class-code-{{ $room->id }}" aria-label="Copy class code for {{ $room->grade }} {{ $room->section ?: $room->name }}" class="rounded-lg border border-green-200 px-3 py-2 text-xs font-bold text-primary hover:bg-paper disabled:opacity-50">Copy</button></div>
@if(!$room->enrollment_open)<p class="mt-2 text-xs font-semibold text-amber-800">Enrollment paused</p>@endif
</article>
@endforeach
@if($classrooms->isEmpty())<a href="{{ route('teacher.settings') }}#classes" class="rounded-xl border border-dashed border-green-200 bg-white px-4 py-3 text-sm font-bold text-primary">Create your teaching sections →</a>@endif
</section>
