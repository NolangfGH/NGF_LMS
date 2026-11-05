<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Staff account recovery | NGF_C++CodeQuest</title>
    <link rel="icon" type="image/png" href="{{ asset('images/branding/nf-codequest-tab-icon.png') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1.25rem; min-height: 100vh; background: #f6fbee; color: #172412; font: 1rem/1.55 system-ui, sans-serif; }
        main { margin: clamp(1rem, 7vh, 4rem) auto; max-width: 33rem; border: 1px solid #cbdcc0; border-radius: 1.25rem; background: white; padding: clamp(1.2rem, 5vw, 2.4rem); }
        img { width: min(13rem, 80%); height: auto; }
        h1 { line-height: 1.15; color: #0b4e00; font-size: 1.8rem; margin: .8rem 0; }
        label { display: block; margin: 1rem 0 .35rem; font-weight: 700; }
        input { width: 100%; padding: .75rem; border: 1px solid #a3b89b; border-radius: .6rem; font: inherit; }
        a { color: #0b4e00; font-weight: 700; }
        button { width: 100%; margin-top: 1rem; padding: .8rem; color: white; background: #0b4e00; border: 0; border-radius: .65rem; font: inherit; font-weight: 750; cursor: pointer; }
        .status { background: #eaf6e2; padding: .8rem; border-radius: .6rem; }
        .errors { color: #842017; background: #fff1ef; padding: .8rem .8rem .8rem 1.75rem; border-radius: .6rem; }
        .help { color: #45643b; font-size: .9rem; }
    </style>
</head>
<body>
<main>
    <img src="{{ asset('images/branding/nf-cpp-codequest-logo.png') }}" alt="NGF C++ CodeQuest" width="1997" height="787">
    <h1>{{ isset($token) ? 'Choose a new password' : 'Recover your staff account' }}</h1>
    @if(session('recovery_notice'))<p role="status" class="status">{{ session('recovery_notice') }}</p>@endif
    @if($errors->any())
        <ul role="alert" class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    @isset($token)
        <p>Set a new password for {{ $email }}. Use at least 10 characters, including a letter and a number.</p>
        <form method="POST" action="{{ route('staff.password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            <label for="new-password">New password</label>
            <input id="new-password" name="password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required>
            <label for="confirm-password">Confirm new password</label>
            <input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" maxlength="72" required>
            <button>Update password</button>
        </form>
    @else
        <p>Teachers, principals, and System Admins use their registered school email as their username.</p>
        @if($mailAvailable)
            <form method="POST" action="{{ route('staff.password.email') }}">
                @csrf
                <label for="school-email">Registered school email</label>
                <input id="school-email" name="email" type="email" autocomplete="username" maxlength="255" value="{{ old('email') }}" required>
                <button>Send reset link</button>
            </form>
        @else
            <p class="status">Email recovery is currently unavailable. Contact your school’s authorized LMS support to recover access.</p>
        @endif
        <p class="help">If you forgot the registered email or cannot open its inbox, contact authorized LMS support so they can verify your identity.</p>
    @endisset
    <p><a href="{{ route('teacher.login') }}">← Staff sign-in</a></p>
</main>
</body>
</html>
