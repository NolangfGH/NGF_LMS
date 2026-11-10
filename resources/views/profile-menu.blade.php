@php
    $profileIsStudent = $profileAccount instanceof \App\Models\Student;
    $profileName = $profileIsStudent ? $profileAccount->nickname : $profileAccount->fullName();
    $profileRole = $profileIsStudent ? 'Student' : $profileAccount->roleLabel();
    $profileSettings = route($profileIsStudent ? 'student.settings' : 'teacher.settings');
    $profileLinks = [
        [$profileSettings.'#profile', 'person', 'My profile'],
        [$profileSettings.'#account', 'manage_accounts', 'Account settings'],
        [$profileSettings.'#notifications', 'notifications', 'Notifications'],
        [$profileSettings.($profileIsStudent ? '#display' : '#preferences'), 'accessibility_new', 'Display & accessibility'],
        [$profileSettings.'#help', 'help', 'Help & support'],
    ];
@endphp
<details class="app-profile-menu" data-profile-menu>
    <summary class="{{ !empty($profileMenuChip) ? 'app-admin-profile-chip' : 'app-admin-profile-link' }}" aria-label="Open {{ $profileRole }} account menu">
        @if($profileIsStudent)
            <span class="app-profile-menu__student-avatar">@include('student-avatar', ['student' => $profileAccount])</span>
        @else
            @include('admin-avatar', ['teacher' => $profileAccount])
        @endif
        @if(!empty($profileMenuChip))<span>{{ $profileRole }}</span>@endif
        <svg class="app-profile-menu__chevron" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </summary>
    <div class="app-profile-menu__panel">
        <div class="app-profile-menu__identity">
            <strong>{{ $profileName }}</strong>
            <span>{{ $profileRole }} · NGF_C++CodeQuest</span>
        </div>
        <nav aria-label="{{ $profileRole }} account options">
            @if($profileIsStudent)
                <a href="{{ route('student.progress') }}"><span class="material-symbols-outlined" aria-hidden="true">school</span>My learning progress</a>
            @endif
            @foreach($profileLinks as [$profileHref, $profileIcon, $profileLabel])
                <a href="{{ $profileHref }}"><span class="material-symbols-outlined" aria-hidden="true">{{ $profileIcon }}</span>{{ $profileLabel }}</a>
            @endforeach
            @if(!$profileIsStudent && $profileAccount->isSchoolAdmin())
                <a href="{{ route('school.admin') }}"><span class="material-symbols-outlined" aria-hidden="true">admin_panel_settings</span>School administration</a>
            @endif
            <div class="app-profile-menu__system">
                @if(!$profileIsStudent && !$profileAccount->isPrincipal())<a href="{{ route('teacher.assessment-activity') }}"><span class="material-symbols-outlined" aria-hidden="true">visibility</span>Assessment page activity</a>@endif
                <a href="{{ route('system.guide') }}"><span class="material-symbols-outlined" aria-hidden="true">menu_book</span>User guide</a>
                <a href="{{ route('mobile.app') }}"><span class="material-symbols-outlined" aria-hidden="true">install_mobile</span>Get the mobile app</a>
                @if(!$profileIsStudent && !$profileAccount->isPrincipal())<a href="{{ route('teacher.parents') }}"><span class="material-symbols-outlined" aria-hidden="true">family_restroom</span>Parent access requests</a>@endif
@if(!$profileIsStudent && $profileAccount->isSchoolAdmin())<a href="{{ route('school.audit') }}"><span class="material-symbols-outlined" aria-hidden="true">history</span>Audit trail reports</a><a href="{{ route('school.donations') }}"><span class="material-symbols-outlined" aria-hidden="true">volunteer_activism</span>Donation settings</a>@endif
@if($profileIsStudent)<a href="{{ route('student.recitations') }}"><span class="material-symbols-outlined" aria-hidden="true">record_voice_over</span>Class recitation</a>@elseif(!$profileAccount->isPrincipal())<a href="{{ route('teacher.recitations') }}"><span class="material-symbols-outlined" aria-hidden="true">record_voice_over</span>Recitation Studio</a>@endif
<a href="{{ route('donations') }}"><span class="material-symbols-outlined" aria-hidden="true">volunteer_activism</span>Support C++ learning</a>
<a href="{{ route('system.about') }}"><span class="material-symbols-outlined" aria-hidden="true">info</span>About the system</a>
                <a href="{{ route('system.about') }}#feedback"><span class="material-symbols-outlined" aria-hidden="true">feedback</span>Suggestions & feedback</a>
            </div>
        </nav>
        <div class="app-profile-menu__signout">
            @if($profileIsStudent)
                <form method="POST" action="{{ route('student.logout') }}">@csrf<button type="submit"><span class="material-symbols-outlined" aria-hidden="true">logout</span>Sign Out</button></form>
            @else
                <a href="{{ route('teacher.logout') }}"><span class="material-symbols-outlined" aria-hidden="true">logout</span>Sign Out</a>
            @endif
        </div>
    </div>
</details>
