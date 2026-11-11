<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher/Admin Login | NGF C++CodeQuest</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{surface:'#f6fbee',primary:'#0b4e00',secondary:'#47673b'}}}};</script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Plus Jakarta Sans',sans-serif}.mono{font-family:'JetBrains Mono',monospace}</style>
    @include('brand-head')
</head>
<body class="min-h-screen bg-surface text-slate-800">
<header class="fixed inset-x-0 top-0 z-20 border-b border-green-900/10 bg-surface/90 backdrop-blur-xl">
    <div class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-5 md:px-10">
        @include('brand-logo',['logoHref'=>route('home'),'logoVariant'=>'header'])
        <a href="{{ route('student.login') }}" class="rounded-lg bg-white px-4 py-2 text-sm font-bold text-primary shadow-sm ring-1 ring-slate-200">Student Sign-In</a>
    </div>
</header>
<main class="flex min-h-screen items-center justify-center px-5 pb-12 pt-28 md:px-10">
    <div class="grid w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-green-900/10 md:grid-cols-2">
        <section class="flex flex-col justify-between bg-green-50 p-7 md:p-10">
            <div><p class="mb-7 inline-flex rounded-full bg-white px-3 py-1.5 text-xs font-bold text-secondary shadow-sm">System Admin / Teacher workspace</p>
                <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-primary md:text-4xl">Empowering the next generation of coders.</h1>
                <p class="mt-5 text-sm leading-7 text-slate-600">Manage your C++ @ Arduino classes, review student work, and guide each learner’s progress.</p>
                <div class="mt-7 rounded-2xl bg-gradient-to-br from-green-100 via-white to-emerald-100 p-6 text-center">
                    <img src="{{ asset('images/branding/nf-codequest-tab-icon.png') }}" alt="NGF CodeQuest logo" width="1100" height="1430" class="mx-auto h-40 w-32 object-contain" decoding="async">
                    <p class="mt-4 text-xs font-bold text-secondary">Build · Measure · Inspire</p>
                </div>
            </div>
            <ul class="mt-6 space-y-2 text-xs font-semibold text-secondary"><li class="rounded-xl bg-white p-3">✓ Review student registrations in your sections</li><li class="rounded-xl bg-white p-3">✓ Assign C++ lessons, quizzes, and coding tasks</li><li class="rounded-xl bg-white p-3">✓ Give private feedback and track learning progress</li></ul>
        </section>
        <section class="p-7 md:p-10"><div class="mx-auto max-w-md">
            <p class="mono text-[10px] font-bold uppercase tracking-[.2em] text-secondary">Teacher/Admin Authentication</p>
            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-primary">Welcome back, teacher</h2>
            <p class="mt-3 text-sm leading-6 text-slate-500">Manage your class, create assignments, and monitor student C++ @ Arduino progress.</p>
            <p class="mono mb-6 mt-8 text-xs font-bold text-secondary">system admin/teacher credentials</p>
            <div id="feedback" role="alert" class="mb-4 hidden rounded-xl border px-4 py-3 text-sm"></div>
            <form id="teacher-login-form" class="space-y-5">
                <label class="block" for="email"><span class="mb-2 block text-sm font-bold">School email or System Admin/Teacher ID <span class="text-red-600">*</span></span>
                    <input id="email" name="identifier" type="text" required autocomplete="username" data-clear-on-entry placeholder="School email or System Admin/Teacher ID" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-green-700 focus:ring-4 focus:ring-green-100">
                </label>
                <div><div class="mb-2 flex items-center justify-between gap-3"><label for="password" class="text-sm font-bold">Password</label><a href="{{ route('staff.password.request') }}" class="text-xs font-semibold text-primary hover:underline">Forgot username or password?</a></div>
                    <div class="relative"><input id="password" name="password" type="password" required autocomplete="current-password" data-clear-on-entry placeholder="Password" class="w-full rounded-xl border border-slate-200 px-4 py-3 pr-14 text-sm outline-none focus:border-green-700 focus:ring-4 focus:ring-green-100"><button id="toggle-password" type="button" aria-label="Show password" class="absolute right-3 top-3 text-xs font-bold text-secondary">Show</button></div>
                </div>
                @include('login-location')
<button id="submit" type="submit" class="w-full rounded-xl bg-primary py-3.5 text-sm font-extrabold text-white shadow-[0_4px_0_#022100] hover:bg-green-800 disabled:opacity-60">Sign In to Classroom Workspace →</button>
            </form>
            <p class="mt-6 text-xs leading-6 text-slate-500">Use the school email you registered with. Only valid teacher/admin credentials can open the workspace.</p>
        </div></section>
    </div>
</main>
<footer class="border-t border-green-900/10 bg-white px-5 py-5 text-center text-xs text-slate-500">© {{ now('Asia/Singapore')->year }} NGF_C++CodeQuest student privacy safeguards</footer>
<script>
const feedback = document.getElementById('feedback');
function showFeedback(message, error = false) {
    feedback.className = 'mb-4 rounded-xl border px-4 py-3 text-sm ' + (error ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-primary');
    feedback.textContent = message;
}
document.getElementById('toggle-password').onclick = event => {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
    event.target.textContent = input.type === 'password' ? 'Show' : 'Hide';
    event.target.setAttribute('aria-label', input.type === 'password' ? 'Show password' : 'Hide password');
};

document.getElementById('teacher-login-form').onsubmit = async event => {
    event.preventDefault();
    const button = document.getElementById('submit');
    button.disabled = true;
    button.textContent = 'Signing in…';
    showFeedback('Verifying teacher/admin credentials…');
    try {
        const response = await fetch('{{ route('teacher.login.store') }}', {
            method: 'POST',
            headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify({identifier: document.getElementById('email').value.trim(), password: document.getElementById('password').value, ...window.ngfLoginLocationFields?.(event.target)})
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'The teacher credentials are incorrect.');
        window.location.assign(result.redirect);
    } catch (error) {
        showFeedback(error.message, true);
        button.disabled = false;
        button.textContent = 'Sign In to Classroom Workspace →';
    }
};
</script>
</body></html>
