<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>Student Registration | CodeQuest C++</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#f6fbee',primary:'#0b4e00',secondary:'#47673b'},fontFamily:{sans:['Plus Jakarta Sans','sans-serif']}}}};</script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
@include('brand-head')</head>
<body class="min-h-screen bg-surface font-sans text-slate-800">
<header class="fixed inset-x-0 top-0 z-40 border-b border-green-900/10 bg-surface/95 shadow-sm backdrop-blur-xl"><div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-5">
@include('brand-logo',['logoHref'=>route('home'),'logoVariant'=>'header'])
<a href="{{ route('student.login') }}" class="rounded-xl border bg-white px-4 py-3 text-sm font-bold text-primary">Student Sign-In</a></div></header>
<main class="mx-auto max-w-5xl px-5 pb-8 pt-24" data-student-registration data-options-url="{{ route('student.register.options') }}" data-verify-url="{{ route('student.register.verify') }}" data-register-url="{{ route('student.register.store') }}">
<p class="text-xs font-bold uppercase tracking-widest text-secondary">Student onboarding</p><h1 class="my-4 text-3xl font-extrabold text-primary">Join your C++ flight deck</h1>
<p class="mb-5 inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-900"><span aria-hidden="true">★</span> Start your coding journey with {{ \App\Models\Student::REGISTRATION_XP }} welcome XP after your teacher approves your registration.</p>
<div class="overflow-hidden rounded-3xl bg-white shadow-lg">
<ol class="grid grid-cols-3 gap-3 border-b bg-green-50 p-5 text-xs font-bold sm:text-sm" aria-label="Registration steps">
<li id="indicator1" aria-current="step" class="text-primary">1. Student & class details</li><li id="indicator2" class="text-slate-400">2. Account setup</li><li id="indicator3" class="text-slate-400">3. Awaiting approval</li></ol>
<div class="grid md:grid-cols-[.65fr_1.35fr]">
<aside class="bg-slate-50 p-7"><div class="rounded-3xl bg-green-100 p-4 text-center"><img src="{{ asset('images/branding/nf-codequest-tab-icon.png') }}" alt="NGF C++ Classroom logo" width="1100" height="1430" class="mx-auto h-40 w-32 object-contain" decoding="async"></div><p class="mt-6 rounded-xl bg-white p-4 text-sm leading-6"><b class="text-primary">Greetings, Coder Cadet!</b> Enter your student details, choose your grade and section, then use your teacher’s class code to join.</p></aside>
<div class="p-6 sm:p-8"><div id="feedback" role="alert" class="mb-5 hidden rounded-xl bg-red-50 p-4 text-sm text-red-800" tabindex="-1"></div>
<form id="enrollmentForm" class="space-y-5">
<h2 class="text-2xl font-extrabold text-primary">Student and class details</h2>
<div class="grid gap-4 sm:grid-cols-2">
<label class="block"><span class="mb-2 block text-sm font-bold">First name *</span><input name="first_name" required maxlength="80" autocomplete="given-name" class="w-full rounded-xl border p-3"></label>
<label class="block"><span class="mb-2 block text-sm font-bold">Middle name (optional)</span><input name="middle_name" maxlength="80" autocomplete="additional-name" class="w-full rounded-xl border p-3"></label>
<label class="block sm:col-span-2"><span class="mb-2 block text-sm font-bold">Surname *</span><input name="surname" required maxlength="80" autocomplete="family-name" class="w-full rounded-xl border p-3"></label>
<label class="block sm:col-span-2"><span class="mb-2 block text-sm font-bold">Name of school *</span><input name="school_name" required maxlength="180" autocomplete="organization" placeholder="Your current school" class="w-full rounded-xl border p-3"></label>
<label class="block sm:col-span-2"><span class="mb-2 block text-sm font-bold">Name of ICT teacher *</span><input name="ict_teacher_name" required maxlength="180" placeholder="Your school ICT teacher’s full name" class="w-full rounded-xl border p-3"></label>
<label class="block"><span class="mb-2 block text-sm font-bold">Grade level *</span><select id="gradeLevel" name="grade_level" required class="w-full rounded-xl border bg-white p-3"><option value="">Choose grade level</option>@foreach($gradeLevels as $grade)<option value="{{ $grade }}" @selected($grade===config('student_registration.grade'))>{{ $grade }}</option>@endforeach</select></label>
<label class="block"><span class="mb-2 block text-sm font-bold">Section *</span><select id="section" name="section" required disabled class="w-full rounded-xl border bg-white p-3"><option value="">Choose grade level first</option></select></label>
</div>
<p id="sectionHelp" class="text-xs text-slate-500" aria-live="polite">Choose your grade and section. Use that section’s class code.</p>
<button type="button" id="refreshClasses" class="text-xs font-bold text-primary underline">Refresh class list</button>
<label class="block"><span class="mb-2 block text-sm font-bold">Class join code *</span><input id="classCode" name="class_code" required maxlength="20" autocomplete="off" class="w-full rounded-xl border p-4 text-center font-mono text-xl uppercase tracking-widest" placeholder="Enter your teacher’s code"></label>
<p class="text-xs text-slate-500">Students from RS and other schools are welcome. Enter your actual school and ICT teacher; a valid class code and teacher approval are still required.</p>
<button type="submit" class="w-full rounded-xl bg-primary p-4 font-bold text-white">Verify Code & Continue →</button>
</form>
<form id="accountForm" class="hidden space-y-5">
<h2 class="text-2xl font-extrabold text-primary">Create your cadet account</h2><p id="verifiedClass" class="rounded-xl bg-green-50 p-3 text-sm text-primary"></p>
<label class="block"><span class="mb-2 block text-sm font-bold">Safe nickname *</span><input name="nickname" required minlength="3" maxlength="40" class="w-full rounded-xl border p-3"></label>
<label class="block"><span class="mb-2 block text-sm font-bold">Student ID *</span><input name="student_id" required minlength="3" maxlength="40" autocomplete="username" class="w-full rounded-xl border p-3"></label>
<label class="block"><span class="mb-2 block text-sm font-bold">Password *</span><input name="password" type="password" required minlength="8" autocomplete="new-password" class="w-full rounded-xl border p-3"><small>At least 8 characters.</small></label>
<label class="block"><span class="mb-2 block text-sm font-bold">Confirm password *</span><input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="w-full rounded-xl border p-3"></label>
<label class="block"><span class="mb-2 block text-sm font-bold">Cadet companion</span><select name="avatar" class="w-full rounded-xl border bg-white p-3"><option>Astro Bot</option><option>Byte Owl</option><option>Solar Cadet</option></select></label>
<label class="flex gap-3 text-xs leading-5"><input type="checkbox" required class="mt-1"><span>I understand that my registration will be reviewed by my class administrator. My teacher can review my learning work, and my nickname may appear in classroom rankings.</span></label>
<div class="flex gap-3"><button type="button" id="backButton" class="rounded-xl border p-3 font-bold">← Back</button><button type="submit" class="flex-1 rounded-xl bg-primary p-3 font-bold text-white">Submit for approval →</button></div>
</form>
<section id="success" class="hidden"><h2 class="text-2xl font-extrabold text-primary">Registration submitted</h2><p class="mt-4 text-sm">Your class administrator will review your details before enabling access. You will receive 100 welcome XP on approval.</p><a href="{{ route('student.registration.status') }}" class="mt-5 inline-block rounded-xl bg-primary p-4 font-bold text-white">Check my registration status →</a></section>
</div></div></div>
</main>
<script type="application/json" id="enrollmentOptions">@json($enrollmentOptions)</script>
<script src="{{ asset('js/student-registration.js') }}?v=20261104" defer></script>
</body></html>
