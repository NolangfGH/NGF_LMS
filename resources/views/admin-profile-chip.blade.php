<a href="{{ route('teacher.settings') }}#profile" class="app-admin-profile-chip" aria-label="Open staff profile">
    @include('admin-avatar')<span>{{ ($teacher ?? \App\Models\TeacherAccount::find(session('teacher_id')))?->roleLabel() ?? 'Staff' }}</span>
</a>
