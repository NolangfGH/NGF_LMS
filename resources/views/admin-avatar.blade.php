@php
    $staffAvatarAccount = $teacher ?? \App\Models\TeacherAccount::find(session('teacher_id'));
    $staffAvatarUrl = $staffAvatarAccount?->avatar_path
        ? route('teacher.settings.avatar', ['teacher' => $staffAvatarAccount->id, 'v' => substr(hash('sha256', $staffAvatarAccount->avatar_path), 0, 12)])
        : asset($staffAvatarAccount?->isSchoolAdmin() ? 'images/branding/system-admin-profile.png' : 'images/branding/nf-codequest-tab-icon.png');
@endphp
<img src="{{ $staffAvatarUrl }}" alt="{{ $staffAvatarAccount?->roleLabel() ?? 'Staff' }} profile photo" class="app-admin-avatar app-admin-avatar--{{ $adminAvatarSize ?? 'small' }}" width="1254" height="1254" decoding="async">
