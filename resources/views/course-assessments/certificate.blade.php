<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $certificate->title }} | NGF_C++CodeQuest</title>
    <link rel="icon" type="image/png" href="{{ asset('images/branding/nf-codequest-tab-icon.png') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f6fbee; color: #172412; font: 1rem/1.6 system-ui, sans-serif; padding: clamp(.8rem, 4vw, 2.5rem); }
        nav { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 0 auto 1rem; max-width: 58rem; }
        a { color: #0b4e00; font-weight: 700; }
        button { background: #0b4e00; color: white; border: 0; border-radius: .6rem; font: inherit; font-weight: 700; padding: .65rem 1rem; cursor: pointer; }
        main { max-width: 58rem; margin: auto; background: white; border: .5rem double #0b4e00; padding: clamp(1.25rem, 5vw, 3.5rem); text-align: center; overflow-wrap: anywhere; }
        img { width: min(19rem, 100%); height: auto; }
        .eyebrow { color: #47673b; font-size: .8rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        h1, h2 { color: #0b4e00; margin: .45rem 0; line-height: 1.15; }
        h1 { font: 700 clamp(1.8rem, 5vw, 3.1rem)/1.1 Georgia, serif; }
        h2 { font-size: clamp(1.6rem, 4vw, 2.4rem); }
        .message { max-width: 38rem; margin: 1.25rem auto; white-space: pre-wrap; }
        footer { border-top: 1px solid #a7bf9c; margin-top: 1.75rem; padding-top: .8rem; font-size: .9rem; }
        @media print {
            @page { size: A4 landscape; margin: 14mm; }
            body { background: white; padding: 0; }
            nav { display: none; }
            main { max-width: none; padding: 1.6rem 2rem; break-inside: avoid; }
            img { width: 14rem; }
        }
    </style>
</head>
<body>
    <nav aria-label="Certificate actions">
        <a href="{{ $backRoute }}">← Back</a>
        <button id="print-certificate" type="button">Print / Save as PDF</button>
    </nav>
    <main>
        <img src="{{ asset('images/branding/nf-cpp-codequest-logo.png') }}" alt="NGF C++ CodeQuest" width="1997" height="787">
        <p class="eyebrow">{{ $certificate->school }}</p>
        <h1>{{ $certificate->title }}</h1>
        <p>Presented to</p>
        <h2>{{ $certificate->recipient }}</h2>
        <p class="message">{{ $certificate->message }}</p>
        <footer>
            <strong>{{ $certificate->issuer }}</strong><br>
            {{ \Illuminate\Support\Carbon::parse($certificate->issued_at)->timezone(config('learning.timezone'))->format('F j, Y') }}
            <span aria-hidden="true"> · </span> Certificate {{ $certificate->certificate_code }}
        </footer>
    </main>
    <script>document.getElementById('print-certificate').addEventListener('click', () => window.print());</script>
</body>
</html>
