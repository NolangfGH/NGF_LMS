<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Student sign-in for CodeQuest C++.">
    <title>Student Sign-In | CodeQuest C++</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            colors: { paper: '#f6fbee', primary: '#0b4e00', 'primary-container': '#146902', secondary: '#47673b', 'secondary-soft': '#c5eab3', muted: '#40493b', panel: '#ffffff' },
            fontFamily: { sans: ['Plus Jakarta Sans', 'ui-sans-serif', 'system-ui'], mono: ['JetBrains Mono', 'ui-monospace', 'monospace'] },
            boxShadow: { tactile: '0 4px 0 0 #022100' }
        } } };
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
@include('brand-head')</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    <header class="fixed inset-x-0 top-0 z-40 border-b border-primary/10 bg-paper/85 shadow-sm backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
            @include('brand-logo',['logoHref'=>route('home'),'logoVariant'=>'header'])
            <a href="{{ route('home') }}" class="text-sm font-bold text-primary hover:underline">Back home</a>
        </div>
    </header>

    <main class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 pb-12 pt-24 sm:px-6">
        <div class="pointer-events-none absolute -left-40 -top-32 h-96 w-96 rounded-full bg-primary/10 blur-3xl"></div><div class="pointer-events-none absolute -bottom-40 -right-20 h-[30rem] w-[30rem] rounded-full bg-secondary-soft/40 blur-3xl"></div>
        <div class="relative z-10 grid w-full max-w-5xl overflow-hidden rounded-3xl border border-primary/10 bg-white shadow-2xl lg:grid-cols-12">
            <section class="relative flex flex-col justify-between overflow-hidden bg-gradient-to-b from-primary via-primary-container to-[#073600] p-6 text-white sm:p-10 lg:col-span-5">
                <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 16px 16px;"></div>
                <div class="relative"><div class="flex justify-center"><div class="inline-flex items-center gap-2 rounded-full border border-lime-200/20 bg-lime-200/20 px-3 py-1 font-mono text-[10px] font-bold uppercase text-lime-100">Students . Mission ready</div></div><h1 class="mt-5 text-3xl font-extrabold tracking-tight sm:text-4xl">Welcome back, Coder Cadet!</h1><p class="mt-3 text-sm leading-6 text-lime-100">Jump straight into today’s C++ and Arduino coding challenge or continue where your class left off.</p></div>
                <div class="relative my-8 flex flex-col items-center"><div class="mb-4 rounded-2xl bg-white px-5 py-3 text-center text-primary shadow-lg"><strong class="block text-base">“Ready to level up your C++ @ Arduino?”</strong><span class="text-xs text-muted">Enter into C++ @ Arduino Classroom</span></div><img src="{{ asset('images/branding/nf-codequest-tab-icon.png') }}" alt="NGF C++ Classroom logo" width="1100" height="1430" class="h-56 w-44 object-contain drop-shadow-xl" decoding="async"></div>
                <div class="relative rounded-2xl border border-white/20 bg-white/10 p-4 text-center backdrop-blur-md"><strong class="block text-sm text-lime-100">NGF C++ @ Arduino Classroom</strong></div>
            </section>

            <section class="p-6 sm:p-10 lg:col-span-7">
                <div class="flex items-start justify-between gap-4"><div><h2 class="text-2xl font-extrabold tracking-tight text-primary sm:text-3xl">Student Sign-In</h2><p class="mt-1 text-sm text-muted">Sign in and continue your C++ @ Arduino adventure.</p></div><span class="hidden rounded-xl bg-paper px-3 py-2 font-mono text-xs font-bold text-secondary sm:inline-flex">⌘ NGF_LMS V1.0</span></div>
                <div id="loginNotice" role="alert" class="mt-6 hidden rounded-2xl border border-primary/20 bg-secondary-soft/50 p-4 text-sm text-primary"></div>
                <form id="studentLoginForm" method="POST" action="{{ route('student.login.store') }}" class="mt-8 space-y-5" onsubmit="submitStudentLogin(event)">
                    @csrf
                    <div><div class="mb-1.5 flex items-center justify-between gap-2"><label for="studentId" class="text-sm font-bold text-primary">Student LRN Number</label><span class="hidden font-mono text-[10px] text-muted sm:inline">Check your ID card</span></div><div class="relative"><span class="material-symbols-outlined absolute left-3 top-3 text-secondary">badge</span><input id="studentId" name="student_id" required type="text" autocomplete="username" data-clear-on-entry spellcheck="false" placeholder="Student LRN Number" class="w-full rounded-xl border border-primary/20 bg-white py-3 pl-11 pr-4 text-sm outline-none transition focus:border-tertiary focus:ring-4 focus:ring-tertiary/10"></div></div>
                    <div><div class="mb-1.5 flex items-center justify-between"><label for="studentPassword" class="text-sm font-bold text-primary">Password</label><button type="button" onclick="showToast('Ask your teacher to reset your student password.')" class="text-xs font-semibold text-primary hover:underline">Forgot code?</button></div><div class="relative"><span class="material-symbols-outlined absolute left-3 top-3 text-secondary">lock</span><input id="studentPassword" name="password" required type="password" autocomplete="current-password" data-clear-on-entry placeholder="Password" class="w-full rounded-xl border border-primary/20 bg-white py-3 pl-11 pr-12 text-sm outline-none transition focus:border-tertiary focus:ring-4 focus:ring-tertiary/10"><button type="button" onclick="togglePassword()" aria-label="Show or hide password" class="absolute right-3 top-2.5 rounded-lg p-1 text-secondary"><span id="eyeIcon" class="material-symbols-outlined">visibility</span></button></div></div>
                    <label class="flex items-center gap-2 text-xs text-muted"><input type="checkbox" checked class="rounded text-primary focus:ring-primary"> Remember me on this Chromebook <span class="ml-auto">🛡 Secure session</span></label>
                    @include('login-location')
<button id="loginButton" type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3.5 font-bold text-white shadow-tactile transition hover:bg-primary-container active:translate-y-0.5">Start Learning <span>→</span></button>
                </form>
                <div class="mt-5 text-center text-sm text-muted">New to CodeQuest? <a href="{{ route('student.register') }}" class="font-bold text-primary hover:underline">Create a student profile →</a></div>
                <div class="mt-7 rounded-2xl border border-primary/10 bg-paper p-4"><div class="flex items-center justify-between gap-3"><div><strong class="block text-sm text-primary">Joining a new class?</strong><p class="mt-1 text-xs text-muted">Use your teacher’s class code during registration.</p></div><a href="{{ route('student.register') }}" class="shrink-0 rounded-lg bg-white px-3 py-2 text-xs font-bold text-primary shadow-sm">Enter Code</a></div></div>
            </section>
        </div>
    </main>

    <footer class="border-t border-primary/10 bg-white px-4 py-5 text-center text-xs text-muted"><span>© {{ now('Asia/Singapore')->year }} NGF_C++CodeQuest student privacy safeguards</span></footer>
    <div id="toast" class="fixed bottom-6 left-1/2 z-50 hidden -translate-x-1/2 rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white shadow-2xl"></div>
    <script>
        function togglePassword() { const input = document.getElementById('studentPassword'); const icon = document.getElementById('eyeIcon'); input.type = input.type === 'password' ? 'text' : 'password'; icon.textContent = input.type === 'password' ? 'visibility' : 'visibility_off'; }
        function showToast(message) { const toast = document.getElementById('toast'); toast.textContent = message; toast.classList.remove('hidden'); clearTimeout(window.toastTimer); window.toastTimer = setTimeout(() => toast.classList.add('hidden'), 3200); }
        async function submitStudentLogin(event) {
            event.preventDefault();
            const id = document.getElementById('studentId').value.trim().toUpperCase();
            const password = document.getElementById('studentPassword').value;
            const button = document.getElementById('loginButton');
            const notice = document.getElementById('loginNotice');
            button.disabled = true;
            button.innerHTML = 'Checking student account…';
            try {
                const response = await fetch('{{ route('student.login.store') }}', {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                    body: JSON.stringify({student_id: id, password, ...window.ngfLoginLocationFields?.(event.target)}),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'The Student ID or password is incorrect.');
                window.location.href = result.redirect;
            } catch (error) {
                const heading = document.createElement('strong');
                heading.textContent = 'Sign-in failed.';
                const detail = document.createElement('p');
                detail.className = 'mt-1';
                detail.textContent = error.message;
                notice.replaceChildren(heading, detail);
                notice.classList.remove('hidden');
                button.disabled = false;
                button.innerHTML = 'Start Learning <span>→</span>';
            }
        }
    </script>
</body>
</html>
