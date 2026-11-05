<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About the system | NGF_C++CodeQuest</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = {theme: {extend: {colors: {paper: '#f6fbee', primary: '#0b4e00', secondary: '#47673b'}, fontFamily: {sans: ['Plus Jakarta Sans', 'sans-serif']}}}};</script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @include('brand-head')
    @if($actor instanceof \App\Models\TeacherAccount)
        @include('teacher-preferences')
    @elseif($actor)
        @include('student-preferences')
    @endif
</head>
<body class="min-h-screen bg-paper font-sans text-slate-800">
<header class="portal-header sticky top-0 z-20 border-b border-green-100 bg-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-2 sm:px-6">
        @include('brand-logo', ['logoVariant' => 'header'])
        <nav aria-label="About navigation" class="flex flex-wrap items-center gap-4 text-sm font-bold text-primary">
            @if($actor)
                <a href="{{ route($actor instanceof \App\Models\TeacherAccount ? $actor->dashboardRoute() : 'student.dashboard') }}">← Your portal</a>
            @else
                <a href="{{ route('home') }}">← Home</a>
            @endif
            <a href="#feedback" class="rounded-xl border border-green-200 px-3 py-2">Give feedback</a>
        </nav>
    </div>
</header>
<main class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 sm:py-12">
    <section class="rounded-3xl bg-primary p-6 text-white sm:p-10">
        <p class="text-xs font-bold uppercase tracking-widest text-green-200">About the system · NGF_LMS V1.0</p>
        <h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">NGF_C++CodeQuest</h1>
        <p class="mt-4 max-w-2xl text-sm leading-7 text-green-50">A classroom learning platform for C++ programming. It brings lessons, coding practice, assessments and teacher feedback into one place, with XP and badges to show learning milestones.</p>
    </section>
    <section aria-label="What the platform does" class="grid gap-4 md:grid-cols-3">
        @foreach([
            ['Learn C++ step by step', 'Trace worked examples, answer lesson checkpoints and practise in Code Lab. Review your progress and classroom activities in the student portal.'],
            ['Plan and teach', 'Teachers can organize sections, prepare ILAW lesson plans, assign activities and review student work.'],
            ['Support classroom improvement', 'Principals can view school learning summaries and give coaching feedback on submitted lesson plans.'],
        ] as [$heading, $description])
            <article class="rounded-2xl border border-green-100 bg-white p-5">
                <h2 class="font-extrabold text-primary">{{ $heading }}</h2>
                <p class="mt-2 text-sm leading-6">{{ $description }}</p>
            </article>
        @endforeach
    </section>
    <section id="feedback" class="scroll-mt-32 rounded-3xl border border-green-100 bg-white p-6 sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-2xl font-extrabold text-primary">How is the LMS working for you?</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6">Tell us what helps you learn or teach, what gets in the way, and what could improve. Feedback and attachments are private to you and System Admins.</p>
            </div>
            @if($actor instanceof \App\Models\TeacherAccount && $actor->isSchoolAdmin())
                <a href="{{ route('school.feedback') }}" class="rounded-xl border border-green-200 px-4 py-3 text-sm font-bold text-primary">Open feedback inbox →</a>
            @endif
        </div>
        @if(session('feedback_notice'))<p role="status" class="mt-4 rounded-xl bg-green-50 p-4 text-sm font-bold text-primary">{{ session('feedback_notice') }}</p>@endif
        @if($actor)
            <p class="mt-4 text-xs font-bold text-secondary">Submitting as {{ $actor instanceof \App\Models\TeacherAccount ? $actor->fullName().' · '.$actor->roleLabel() : $actor->nickname.' · Student' }}</p>
            @if($errors->any())
                <ul role="alert" class="mt-4 list-inside list-disc rounded-xl bg-red-50 p-4 text-sm text-red-800">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('system.feedback.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="feedback-category" class="block text-sm font-bold text-primary">Feedback type</label>
                    <select id="feedback-category" name="category" class="mt-2 w-full rounded-xl border border-green-200 p-3 text-sm sm:max-w-sm">
                        @foreach($categories as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="feedback-message" class="block text-sm font-bold text-primary">Comment or suggestion</label>
                    <textarea id="feedback-message" name="message" rows="5" maxlength="3000" class="mt-2 w-full rounded-xl border border-green-200 p-3 text-sm" placeholder="What happened, and what would make the LMS better?">{{ old('message') }}</textarea>
                </div>
                <div>
                    <label for="feedback-image" class="block text-sm font-bold text-primary">Screenshot or image</label>
                    <input id="feedback-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="feedback-image-help" class="mt-2 block max-w-full text-sm">
                    <p id="feedback-image-help" class="mt-2 text-xs leading-5 text-secondary">Add text, an image, or both. JPEG, PNG or WebP up to 2 MB. Crop out passwords, names and private classroom records before uploading.</p>
                </div>
                <button class="rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white">Send private feedback</button>
            </form>
        @else
            <p class="mt-4 rounded-xl bg-paper p-4 text-sm">Sign in to your student or staff account, then return here to share feedback. <a href="{{ route('student.login') }}" class="font-bold text-primary underline">Student sign-in →</a></p>
        @endif
    </section>
    @if($entries)
        <section aria-labelledby="my-feedback">
            <h2 id="my-feedback" class="mb-4 text-xl font-extrabold text-primary">Your submissions</h2>
            @include('system-feedback-entries')
        </section>
    @endif
</main>
</body>
</html>
