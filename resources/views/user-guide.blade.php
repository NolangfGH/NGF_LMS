@extends('layouts.community', ['pageTitle' => 'User guide'])
@section('content')
<section class="rounded-3xl bg-primary p-7 text-white sm:p-10"><p class="text-xs font-bold uppercase tracking-widest">NGF_C++CodeQuest · Help center</p><h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">Your guide to learning and teaching</h1><p class="mt-4 max-w-3xl leading-7">Find the next step, understand a feature, or check how your learning records work. Choose your role below to focus on the instructions you need.</p><div class="mt-5 flex flex-wrap gap-3 text-sm font-bold"><a href="{{ route('student.login') }}" class="rounded-xl bg-white px-4 py-3 text-primary">Student sign-in →</a><a href="{{ route('system.about') }}#feedback" class="rounded-xl border border-white/50 px-4 py-3">Send a suggestion</a></div></section>
<section class="mt-6 rounded-2xl border bg-white p-5" aria-label="Find help" data-guide>
    <div class="grid gap-4 sm:grid-cols-[1fr_14rem_auto]"><label class="text-sm font-bold">Search this guide<input data-guide-search type="search" placeholder="e.g. pre-test, XP, attendance…" class="mt-2 w-full rounded-xl border p-3"></label><label class="text-sm font-bold">I am a<select data-guide-role class="mt-2 w-full rounded-xl border bg-white p-3"><option value="all">All users</option><option value="student">Student</option><option value="teacher">Teacher</option><option value="principal">Principal</option><option value="admin">System Admin</option><option value="parent">Parent</option></select></label><button data-guide-clear type="button" class="self-end rounded-xl border px-5 py-3 font-bold">Clear</button></div><p data-guide-count role="status" aria-live="polite" class="mt-3 text-xs text-secondary">{{ count(config('user_guide')) }} help topics</p>
</section>
<div class="mt-6 grid items-start gap-6 lg:grid-cols-[15rem_1fr]">
    <nav aria-label="Guide contents" class="rounded-2xl border bg-white p-5"><h2 class="font-bold text-primary">On this page</h2><ol class="mt-3 space-y-3 text-sm">@foreach(config('user_guide') as $topic)<li data-guide-link="{{ $topic['id'] }}"><a href="#{{ $topic['id'] }}" class="underline decoration-green-200 underline-offset-4">{{ $topic['title'] }}</a></li>@endforeach</ol></nav>
    <div class="space-y-5">
        @foreach(config('user_guide') as $topic)
            <article id="{{ $topic['id'] }}" data-guide-topic data-guide-roles="{{ implode(' ', $topic['roles']) }}" class="scroll-mt-28 rounded-2xl border bg-white p-6 sm:p-8"><p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ implode(' · ', array_map(fn($role) => ['student'=>'Student','teacher'=>'Teacher','principal'=>'Principal','admin'=>'System Admin','parent'=>'Parent'][$role], $topic['roles'])) }}</p><h2 class="mt-2 text-xl font-bold text-primary">{{ $topic['title'] }}</h2><p class="mt-2 text-sm text-secondary">{{ $topic['summary'] }}</p><ol class="mt-5 list-decimal space-y-4 pl-5 text-sm leading-7">@foreach($topic['steps'] as $step)<li class="pl-1">{{ $step }}</li>@endforeach</ol><a href="#" class="mt-5 inline-block text-xs font-bold underline">Back to top ↑</a></article>
        @endforeach
        <p data-guide-empty hidden class="rounded-2xl border bg-white p-6">No matching help topics. Try a shorter search or choose All users.</p>
    </div>
</div>
<script src="{{ asset('js/user-guide.js') }}" defer></script>
@endsection
