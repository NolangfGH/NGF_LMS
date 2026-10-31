@php
    $studentUiPreferences = \App\Models\Student::select('id','preferences')->find(session('student_id'))?->effectivePreferences() ?? config('student_settings.defaults');
@endphp
<link rel="stylesheet" href="{{ asset('css/student-preferences.css') }}">
<script>
document.documentElement.dataset.studentText = @json($studentUiPreferences['text_size']);
document.documentElement.dataset.studentContrast = @json($studentUiPreferences['high_contrast'] ? 'high' : 'standard');
document.documentElement.dataset.studentMotion = @json($studentUiPreferences['reduce_motion'] ? 'reduce' : 'standard');
</script>
