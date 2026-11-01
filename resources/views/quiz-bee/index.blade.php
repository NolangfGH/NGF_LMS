@extends($teacherRole ? 'layouts.teacher-learning' : 'layouts.student', ['pageTitle'=>'Quiz Bee Arena','active'=>'challenges'])
@section('content')
<link rel="stylesheet" href="{{ asset('css/quiz-bee.css') }}?v=1">
<div class="bee-shell">
<a class="bee-link" href="{{ route($teacherRole ? 'teacher.challenges' : 'student.challenges') }}">← Challenge Studio</a>
<div class="bee-heading"><div><p class="bee-eyebrow">Think fast · Learn together</p><h1>Quiz Bee Arena</h1><p>Individual coders or groups of up to five. Up to 40 entries in each match.</p></div>@if($teacherRole)<a class="bee-button bee-primary" href="{{ route('teacher.quiz-bee.create') }}">＋ Create Quiz Bee</a>@endif</div>
<div class="bee-rewards"><p><strong>+10 XP</strong> each correct answer</p><p><strong>+5 XP</strong> correct within 30 seconds</p><p><strong>+100 XP</strong> perfect regular-question match</p><p><strong>60 seconds</strong> per question</p></div>
<p class="bee-notice">Easy → Average → Difficult. Each level has up to 15 regular questions and five tie-breakers. Your teacher hosts each question. Groups share one captain's answer and every member earns the team's XP. Match standings use correct answers; XP speed bonuses do not break ties.</p>
<p><a class="bee-link" href="{{ route($teacherRole ? 'teacher.quiz-bee.index' : 'student.quiz-bee.index') }}">Refresh matches ↻</a></p>
<div class="bee-cards">@forelse($matches as $match)<article class="bee-card"><p class="bee-eyebrow">{{ ucfirst($match->category) }} · {{ ucfirst($match->status) }}</p><h2>{{ $match->title }}</h2><p>{{ collect($match->questions)->sum(fn ($level)=>count($level['regular'])) }} regular questions · three levels</p><a class="bee-button bee-primary" href="{{ route(($teacherRole ? 'teacher' : 'student').'.quiz-bee.show',$match) }}">{{ $match->status==='completed' ? 'View results' : 'Open match' }} →</a></article>@empty<article class="bee-card"><h2>{{ $teacherRole ? 'Ready to host your first Quiz Bee?' : 'No Quiz Bee invitations yet' }}</h2><p>{{ $teacherRole ? 'Create teams, review the question banks, then publish invitations to your students.' : 'Your teacher will invite you to an individual or group match. Check your challenge notifications.' }}</p></article>@endforelse</div>
{{ $matches->links() }}
</div>
@endsection
