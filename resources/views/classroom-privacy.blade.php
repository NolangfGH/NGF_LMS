@extends('layouts.community', ['pageTitle' => 'Classroom Privacy'])

@section('content')
<section class="rounded-3xl border border-green-200 bg-white p-6 shadow-sm sm:p-10">
    <a href="{{ route('home') }}#privacy" class="text-sm font-bold text-primary underline">← Back to the homepage</a>
    <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-primary sm:text-4xl">Classroom Privacy</h1>
    <p class="mt-3 max-w-3xl leading-7 text-slate-700">See what NGF_C++CodeQuest records, who can view it, and which information you can choose to share.</p>
    <nav aria-label="On this page" class="mt-6 flex flex-wrap gap-2 text-sm font-bold">
        @foreach(['records' => 'What is recorded', 'access' => 'Who can see it', 'choices' => 'Your choices', 'help' => 'Get help'] as $anchor => $label)
            <a href="#{{ $anchor }}" class="rounded-xl border border-green-200 bg-paper px-4 py-2 text-primary hover:bg-green-100">{{ $label }}</a>
        @endforeach
    </nav>
</section>

<section id="records" class="mt-6 scroll-mt-36 rounded-2xl border border-green-200 bg-white p-6 sm:p-8" aria-labelledby="records-title">
    <h2 id="records-title" class="text-xl font-extrabold text-primary">What is recorded</h2>
    <dl class="mt-4 grid gap-5 text-sm leading-7 md:grid-cols-2">
        <div><dt class="font-bold text-primary">Accounts and enrollment</dt><dd>Registration records include the name, school or student ID, and classroom details supplied for approval. Students from other schools may provide ICT teacher contact details.</dd></div>
        <div><dt class="font-bold text-primary">Learning activity</dt><dd>The platform saves lesson progress, assessment responses and scores, code drafts and submissions, teacher feedback, XP, badges, and classroom attendance.</dd></div>
        <div><dt class="font-bold text-primary">Private discussions and uploads</dt><dd>Blog responses and their replies are visible to the author and responsible teacher. A response can contain text, an image or an audio recording.</dd></div>
        <div><dt class="font-bold text-primary">Sign-in and assessment activity</dt><dd>Audit records include recorded sign-in and sign-out times. During an active assessment, the browser may report tab visibility, focus and fullscreen changes for teacher review. The log alone does not determine a score or penalty.</dd></div>
    </dl>
</section>

<section id="access" class="mt-6 scroll-mt-36 rounded-2xl border border-green-200 bg-white p-6 sm:p-8" aria-labelledby="access-title">
    <h2 id="access-title" class="text-xl font-extrabold text-primary">Who can see it</h2>
    <dl class="mt-4 grid gap-5 text-sm leading-7 md:grid-cols-2">
        <div><dt class="font-bold text-primary">Visitors</dt><dd>The homepage shows course topics and platform totals. Class join codes, student identities, and individual results require the appropriate signed-in access.</dd></div>
        <div><dt class="font-bold text-primary">Students and classmates</dt><dd>Students can see their own work, grades and feedback. The classroom leaderboard shows ranks and XP with the nickname or alias permitted by each student's settings.</dd></div>
        <div><dt class="font-bold text-primary">Teachers and school staff</dt><dd>Teachers review enrollment and learning records for their own sections. Principals have a read-only school overview. System Admin manages staff access and can review audit records, including an approximate location if a user shared one.</dd></div>
        <div><dt class="font-bold text-primary">Parents and guardians</dt><dd>A parent account can see available learning summaries for a child after the responsible teacher approves the link.</dd></div>
    </dl>
</section>

<section id="choices" class="mt-6 scroll-mt-36 rounded-2xl border border-green-200 bg-white p-6 sm:p-8" aria-labelledby="choices-title">
    <h2 id="choices-title" class="text-xl font-extrabold text-primary">Your choices</h2>
    <ul class="mt-4 list-disc space-y-3 pl-5 text-sm leading-7">
        <li><b>Leaderboard appearance.</b> In Student Settings → Privacy, choose whether classmates see your nickname and avatar. Turning it off uses a generic alias while keeping rank and XP visible.</li>
        <li><b>Approximate sign-in location.</b> Choose whether to share it. Your browser asks for permission, then the sign-in form rounds the location before sending it. Sign-in works without location sharing.</li>
        <li><b>Camera and microphone.</b> Use the camera for a teacher-reviewed attendance check-in when requested, or the microphone to record a blog reply. The browser asks for permission when you use those controls. Ask your teacher for an alternative if you cannot provide a check-in image.</li>
        <li><b>External coding tools.</b> Opening an example or draft in OneCompiler shares that code with an external service. Keep passwords and personal details out of code you send there.</li>
    </ul>
</section>

<section id="help" class="mt-6 scroll-mt-36 rounded-2xl border border-green-200 bg-green-50 p-6 sm:p-8" aria-labelledby="help-title">
    <h2 id="help-title" class="text-xl font-extrabold text-primary">Get help with a classroom record</h2>
    <p class="mt-3 max-w-3xl text-sm leading-7">Contact your teacher about an incorrect class record or a question about sharing. Contact your school's System Admin for account access or privacy concerns. Leave passwords, join codes and private student documents out of general feedback.</p>
    <a href="{{ route('system.guide') }}#attendance-parents" class="mt-4 inline-block text-sm font-bold text-primary underline">Read the user guide →</a>
</section>
@endsection
