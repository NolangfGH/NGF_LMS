<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login | CodeQuest C++</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#f6fbee',primary:'#0b4e00',secondary:'#47673b',mint:'#d9f5ef',navy:'#182b49'}}}};</script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Plus Jakarta Sans',sans-serif}.mono{font-family:'JetBrains Mono',monospace}</style>
    <meta name="csrf-token" content="{{ csrf_token() }}">
@include('brand-head')</head>
<body class="min-h-screen bg-surface text-slate-800">
    <header class="fixed inset-x-0 top-0 z-20 border-b border-green-900/10 bg-surface/90 backdrop-blur-xl">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 md:px-10">
            @include('brand-logo',['logoHref'=>route('home'),'logoVariant'=>'header'])
            <div class="hidden items-center gap-3 md:flex"><span class="rounded-full bg-green-100 px-3 py-1.5 text-xs font-semibold text-green-900">● All systems operational</span><a href="{{ route('student.login') }}" class="rounded-lg bg-white px-4 py-2 text-sm font-bold text-primary shadow-sm ring-1 ring-slate-200">Student Sign-In</a></div>
        </div>
    </header>

    <main class="flex min-h-screen items-center justify-center px-5 pb-16 pt-28 md:px-10">
        <div class="grid w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-green-900/10 md:grid-cols-2">
            <section class="relative flex flex-col justify-between overflow-hidden bg-green-50 p-7 md:p-10"><div><div class="mb-8 inline-flex rounded-full bg-white px-3 py-1.5 text-xs font-bold text-secondary shadow-sm">System Admin workspace</div><h1 class="max-w-md text-3xl font-black leading-tight tracking-tight text-primary md:text-4xl">Empowering the next generation of coders.</h1><p class="mt-5 max-w-md text-sm leading-7 text-slate-600">Manage student learning pods, track live syntax diagnostics, and inspect compiler execution across your C++ sections.</p><div class="mt-8 overflow-hidden rounded-2xl bg-white shadow-sm"><div class="grid h-48 place-items-center bg-gradient-to-br from-green-100 via-white to-emerald-200"><div class="text-center"><div class="mx-auto grid h-20 w-20 place-items-center rounded-3xl bg-primary text-3xl font-black text-lime-200 shadow-[0_6px_0_#022100]">C++</div><p class="mt-4 text-xs font-bold text-secondary">Build · Measure · Inspire</p></div></div></div></div><div class="mt-8 space-y-2 text-xs font-semibold text-slate-700"><p class="rounded-full bg-white px-3 py-2 shadow-sm">✓ Live LLVM telemetry & infinite-loop warnings</p><p class="rounded-full bg-white px-3 py-2 shadow-sm">✓ Automated C++ auto-grader & milestone analytics</p><p class="rounded-full bg-white px-3 py-2 shadow-sm">✓ FERPA & COPPA compliant workspace</p></div></section>
            <section class="p-7 md:p-10"><div class="mx-auto max-w-md"><p class="mono text-[10px] font-bold uppercase tracking-[.2em] text-secondary">District authentication</p><h2 class="mt-2 text-3xl font-black tracking-tight text-primary">Welcome back, teacher</h2><p class="mt-3 text-sm leading-6 text-slate-500">Manage your class, create assignments, and monitor student C++ progress.</p><div class="mt-7 space-y-3"><button type="button" class="flex w-full items-center justify-center gap-3 rounded-xl border border-slate-200 bg-white py-3 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50" onclick="login('Google Classroom')"><span class="grid h-5 w-5 place-items-center rounded-full bg-blue-500 text-xs font-black text-white">G</span> Sign in with Google Classroom</button><div class="grid grid-cols-2 gap-3"><button type="button" class="rounded-xl border border-slate-200 bg-slate-50 py-3 text-xs font-bold text-slate-700" onclick="login('Clever')">▣ Clever SSO</button><button type="button" class="rounded-xl border border-slate-200 bg-slate-50 py-3 text-xs font-bold text-slate-700" onclick="login('ClassLink')">↗ ClassLink LTI</button></div></div><div class="my-7 flex items-center gap-3"><div class="h-px flex-1 bg-slate-200"></div><span class="mono text-[10px] uppercase tracking-widest text-slate-400">or system admin credentials</span><div class="h-px flex-1 bg-slate-200"></div></div><div id="feedback" class="mb-4 hidden rounded-xl border px-4 py-3 text-xs font-semibold"></div><form onsubmit="event.preventDefault();login('District credentials')" class="space-y-5"><label class="block"><span class="mb-2 block text-sm font-bold text-slate-700">School email or System Admin ID <em class="text-red-600">*</em></span><input id="email" type="text" required value="m.rivera@oakmiddle.edu" placeholder="m.rivera@oakmiddle.edu or T-84029" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none transition focus:border-green-700 focus:ring-4 focus:ring-green-100"></label><label class="block"><span class="mb-2 flex justify-between text-sm font-bold text-slate-700">Password <button type="button" class="text-xs text-primary hover:underline" onclick="forgot()">Forgot password?</button></span><div class="relative"><input id="password" type="password" required value="OakCreek2025!" class="w-full rounded-xl border border-slate-200 px-4 py-3 pr-12 text-sm outline-none transition focus:border-green-700 focus:ring-4 focus:ring-green-100"><button type="button" class="absolute right-3 top-2.5 text-slate-400" onclick="togglePassword()">◉</button></div></label><label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input type="checkbox" checked class="accent-green-700"> Remember me on this classroom workstation</label><button id="submit" type="submit" class="w-full rounded-xl bg-primary py-3.5 text-sm font-black text-white shadow-[0_4px_0_#022100] transition hover:bg-green-800 active:translate-y-0.5">Sign In to Classroom Workspace →</button></form><div class="mt-8 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-5 text-[10px] font-bold uppercase tracking-wider text-slate-500"><span>✓ FERPA & COPPA</span><span>🔒 District Enclave</span><a href="#support" class="hover:text-primary">IT Support</a></div></div></section>
        </div>
    </main>
    <footer class="border-t border-green-900/10 bg-green-50 px-5 py-5 text-center text-xs text-slate-500">© 2024–2025 CodeQuest CS Education · Privacy Policy · Terms of Service</footer>
    <script>
        function togglePassword(){const input=document.getElementById('password');input.type=input.type==='password'?'text':'password'}
        function login(provider){const feedback=document.getElementById('feedback'),button=document.getElementById('submit');feedback.className='mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-xs font-semibold text-green-900';feedback.textContent='Authenticating with '+provider+'…';feedback.classList.remove('hidden');button.disabled=true;button.textContent='Verifying credentials…';setTimeout(()=>{window.location.href='{{ route('teacher.dashboard') }}'},700)}
        function forgot(){const feedback=document.getElementById('feedback');feedback.className='mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-xs font-semibold text-blue-900';feedback.textContent='Password reset instructions will be sent to your district email.';feedback.classList.remove('hidden')}
    </script>
    <script>
        async function login(provider) {
            const feedback = document.getElementById('feedback');
            const button = document.getElementById('submit');
            const identifier = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            feedback.className = 'mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-xs font-semibold text-blue-900';
            feedback.textContent = 'Verifying teacher credentials…';
            feedback.classList.remove('hidden');
            button.disabled = true;
            button.textContent = 'Signing in…';

            try {
                const response = await fetch('{{ route('teacher.login.store') }}', {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                    body: JSON.stringify({identifier, password}),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'The teacher credentials are incorrect.');
                window.location.href = result.redirect;
            } catch (error) {
                feedback.className = 'mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-800';
                feedback.textContent = error.message;
                button.disabled = false;
                button.textContent = 'Sign In to Classroom Workspace →';
            }
        }
    </script>
</body>
</html>
