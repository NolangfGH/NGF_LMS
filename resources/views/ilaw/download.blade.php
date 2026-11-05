<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $content['title'] ?? $lesson['title'] }} · ILAW lesson plan</title>
    <style>
        body { max-width: 800px; margin: 2rem auto; padding: 0 1rem; font: 15px/1.5 system-ui, sans-serif; color: #172412; }
        h1, h2 { color: #0b4e00; }
        section { border-top: 1px solid #b9c9b4; margin-top: 1.25rem; }
        dt { font-weight: bold; }
        dd { white-space: pre-wrap; overflow-wrap: anywhere; margin: 0 0 0.75rem; }
        @media print { body { margin: 0; } h2, dt { break-after: avoid; } }
    </style>
</head>
<body>
    <h1>ILAW lesson plan</h1>
    <p>{{ $teacher->fullName() }} · {{ $teacher->school }} @isset($lesson['term']) · {{ $lesson['term'] }} @endisset</p>
    <p>{{ ucfirst($plan?->status ?? 'worksheet') }}</p>
    @include('ilaw.content')
    @if($plan?->status === 'evaluated' && $plan->review_comment)
        <section><h2>Principal’s coaching comment</h2><dl><dd>{{ $plan->review_comment }}</dd></dl></section>
    @endif
    <p>Framework reference <a href="{{ \App\Services\IlawPlanTemplate::ORDER_URL }}">DepEd Order No. 016, s. 2026</a></p>
</body>
</html>
