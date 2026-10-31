<a href="{{ route('teacher.settings') }}#profile" class="app-admin-profile-link" aria-label="Open {{ ($teacher ?? \App\Models\TeacherAccount::find(session('teacher_id')))?->roleLabel() ?? 'Staff' }} profile" title="{{ ($teacher ?? \App\Models\TeacherAccount::find(session('teacher_id')))?->roleLabel() ?? 'Staff' }} profile">
    @include('admin-avatar')
</a>
