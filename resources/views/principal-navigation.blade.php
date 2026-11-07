@foreach(\App\Services\SchoolOverviewService::TABS as $principalTab=>$principalLabel)
@php
    $principalIcon = match ($principalTab) {
        'overview' => 'space_dashboard',
        'students' => 'school',
        'achievements' => 'military_tech',
        'leaderboard' => 'leaderboard',
        'lessons' => 'menu_book',
        'quizzes' => 'quiz',
        'assignments' => 'assignment',
        'challenges' => 'sports_esports',
        default => 'dashboard',
    };
@endphp
<a href="{{ route('school.overview',['tab'=>$principalTab]) }}" @if(request()->routeIs('school.overview') && request('tab','overview') === $principalTab) aria-current="page" @endif class="principal-menu-link">
    <span class="material-symbols-outlined" aria-hidden="true">{{ $principalIcon }}</span>
    <span>{{ $principalLabel }}</span>
</a>
@endforeach
<a href="{{ route('school.ilaw.index') }}" @if(request()->routeIs('school.ilaw.*')) aria-current="page" @endif class="principal-menu-link">
    <span class="material-symbols-outlined" aria-hidden="true">fact_check</span>
    <span>ILAW Lesson Plan Reviews</span>
</a>
<a href="{{ route('system.about') }}" @if(request()->routeIs('system.about')) aria-current="page" @endif class="principal-menu-link">
    <span class="material-symbols-outlined" aria-hidden="true">info</span>
    <span>About the System</span>
</a>
<a href="{{ route('teacher.settings') }}" @if(request()->routeIs('teacher.settings')) aria-current="page" @endif class="principal-menu-link">
    <span class="material-symbols-outlined" aria-hidden="true">settings</span>
    <span>Account Settings</span>
</a>
