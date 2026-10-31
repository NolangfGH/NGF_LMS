@php
    $teacherUiPreferences = \App\Models\TeacherAccount::select('id','preferences')->find(session('teacher_id'))?->effectivePreferences() ?? config('teacher_settings.defaults');
@endphp
<link rel="stylesheet" href="{{ asset('css/teacher-preferences.css') }}">
<script>
document.documentElement.dataset.teacherText = @json($teacherUiPreferences['text_size']);
document.documentElement.dataset.teacherContrast = @json($teacherUiPreferences['high_contrast'] ? 'high' : 'standard');
document.documentElement.dataset.teacherMotion = @json($teacherUiPreferences['reduce_motion'] ? 'reduce' : 'standard');
</script>
