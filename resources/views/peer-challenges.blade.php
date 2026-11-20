@extends('layouts.student', ['pageTitle'=>'Peer Coding Matches','active'=>'peer'])
@section('content')
<section class="rounded-3xl border border-green-200 bg-white p-5 shadow-card sm:p-8">
    <p class="text-xs font-bold uppercase tracking-widest text-secondary">Learner invitations · {{ $classroom->grade }}</p><h1 class="mt-2 text-3xl font-extrabold text-primary">Student-to-student coding matches</h1>
    <p class="mt-3 max-w-3xl text-sm leading-7">Choose a learner in your grade and an unlocked lesson. Your teacher reviews the task first. When learners have different teachers, both teachers approve. The opponent then accepts before the shared timer starts.</p>
    <p class="mt-3 text-xs leading-6 text-secondary">Your match name: <b>{{ $student->leaderboard_visible ? $student->nickname : $student->rankingAlias() }}</b>. Classmates’ nickname privacy settings apply. You can have up to three open peer requests or matches.</p>
    @if(session('peer_notice'))<p role="status" class="mt-4 rounded-xl bg-paper p-4 text-sm font-bold">{{ session('peer_notice') }}</p>@endif
    @if($errors->any())<div role="alert" class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-900"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="GET" action="{{ route('student.peer-challenges') }}" class="mt-5 grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]">
        <label class="text-sm font-bold">Course<select name="course" class="mt-2 block w-full rounded-xl border bg-paper p-3 font-normal">@foreach(\App\Services\LearningCourses::TITLES as $optionCourse=>$optionLabel)<option value="{{ $optionCourse }}" @selected($course===$optionCourse)>{{ $optionLabel }}</option>@endforeach</select></label>
        <label class="text-sm font-bold">Find a nickname or exact privacy alias<input name="q" minlength="2" maxlength="60" value="{{ $filters['q']??'' }}" placeholder="Nickname or Coder-…" class="mt-2 block w-full rounded-xl border p-3 font-normal"></label>
        <button class="rounded-xl border border-green-200 bg-paper px-4 py-3 text-sm font-bold">Find learners</button>
    </form>
</section>
<div class="mt-6 grid items-start gap-5 lg:grid-cols-2">
    <section class="min-w-0 rounded-3xl border border-green-200 bg-white p-5 sm:p-6"><h2 class="text-xl font-bold text-primary">Learners in {{ $classroom->grade }}</h2><p class="mt-2 text-xs leading-6 text-secondary">Only approved learners with an active supervising teacher appear here.</p><div class="mt-4 space-y-2">
        @forelse($directory as $peer)<article class="flex items-center justify-between gap-3 rounded-xl border border-green-100 bg-paper p-3"><p class="min-w-0 break-words text-sm font-bold">{{ $peer->rankingName($student->id) }}</p><a class="shrink-0 rounded-xl border border-green-200 bg-white px-4 py-2 text-xs font-bold" href="{{ route('student.peer-challenges',['course'=>$course,'opponent'=>$peer->id]) }}#request-peer-match">Choose →</a></article>@empty<p class="rounded-xl bg-paper p-4 text-sm">No learners match this search. Clear the search to browse your grade.</p>@endforelse
    </div><div class="mt-4">{{ $directory->links() }}</div></section>
    <section id="request-peer-match" class="min-w-0 scroll-mt-24 rounded-3xl border border-green-200 bg-white p-5 sm:p-6"><h2 class="text-xl font-bold text-primary">Request a match</h2>
        @if($selectedOpponent)<p class="mt-3 text-sm">Opponent: <b>{{ $selectedOpponent->rankingName($student->id) }}</b></p>
            @if($availableLessons->isNotEmpty())<form method="POST" action="{{ route('student.peer-challenges.store') }}" class="mt-4 space-y-4">@csrf<input type="hidden" name="opponent" value="{{ $selectedOpponent->id }}"><input type="hidden" name="course" value="{{ $course }}">
                <label class="block text-sm font-bold">Lesson / topic<select name="lesson" required class="mt-2 block w-full rounded-xl border bg-paper p-3 font-normal"><option value="">Choose an unlocked topic</option>@foreach($availableLessons as $key=>$lesson)<option value="{{ $key }}" @selected(old('lesson')===$key)>{{ $lesson['title'] }}</option>@endforeach</select></label>
                <p class="text-xs leading-6 text-secondary">Your teacher can refine the task, expected output, time limit and winner reward. Sending this request counts as your acceptance. Each coder gets one final submission; your teacher verifies the winner.</p>
                <button class="rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white">Send request for teacher approval →</button>
            </form>@else<p class="mt-4 rounded-xl bg-paper p-4 text-sm leading-7">Complete the Student Reasoning Challenge, the course pre-test and any preceding lesson checkpoints to unlock a topic. <a class="font-bold underline" href="{{ route('student.assessments.index',['course'=>$course]) }}">Open course assessments →</a></p>@endif
        @else<p class="mt-3 text-sm leading-7">Choose a learner from the list, then select the lesson for your match.</p>@endif
    </section>
</div>
<section class="mt-6 rounded-3xl border border-green-200 bg-white p-5 sm:p-6"><h2 class="text-2xl font-bold text-primary">Your requests and matches</h2><div class="mt-4 grid gap-4 sm:grid-cols-2">
    @forelse($requests as $record)
        @php($other=$record->participants->first(fn($participant)=>$participant->student_id!==$student->id))
        @php($reviews=$approvals->get($record->id,collect()))
        <article class="rounded-2xl border border-green-100 bg-paper p-4"><p class="text-xs font-bold uppercase text-secondary">{{ \App\Services\LearningCourses::title($record->course) }} · {{ str_replace('_',' ',$record->status) }}</p><h3 class="mt-2 break-words font-bold">{{ $record->title }}</h3><p class="mt-2 text-sm">With {{ $other?->student?->rankingName($student->id) ?? 'Former learner' }}</p><p class="mt-2 text-xs">{{ $reviews->where('decision','approved')->count() }}/{{ $reviews->count() }} supervising teachers approved · {{ $record->time_limit_minutes }} minutes</p>
            @if($record->status==='awaiting_approval')<p class="mt-3 text-xs leading-6">Waiting for teacher approval. Coding and acceptance stay locked.</p>@else<a class="mt-3 inline-block rounded-xl bg-primary px-4 py-3 text-xs font-bold text-white" href="{{ route('student.matches.show',$record) }}">Open match / result →</a>@endif
            @if(in_array($record->status,['awaiting_approval','inviting'],true))<form method="POST" action="{{ route('student.peer-challenges.withdraw',$record) }}" class="mt-3">@csrf<button class="text-xs font-bold text-secondary underline">{{ $record->requested_by_student_id===$student->id ? 'Withdraw request' : 'Decline request' }}</button></form>@endif
        </article>
    @empty<p class="rounded-xl bg-paper p-4 text-sm">No peer requests yet. Choose a classmate to request your first match.</p>@endforelse
</div><div class="mt-5">{{ $requests->links() }}</div></section>
@endsection
