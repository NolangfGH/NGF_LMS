<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class HomepageService
{
    public function snapshot(?int $studentId = null, ?int $teacherId = null): array
    {
        $now = now(config('learning.timezone'));
        $courses = [
            'cpp' => ['title' => 'C++ Programming', 'heading' => 'Build your C++ foundations', 'grade' => 'Grade 8', 'lessons' => config('lessons')],
            'arduino-uno' => ['title' => 'Arduino Uno Robotics', 'heading' => 'Build with Arduino Uno', 'grade' => 'Grade 9', 'lessons' => config('arduino_curriculum.lessons')],
        ];
        $catalog = [];
        foreach ($courses as $course => &$summary) {
            $number = 0;
            foreach ($summary['lessons'] as $key => $lesson) {
                $catalog[] = [
                    'key' => $key, 'course' => $course, 'number' => ++$number,
                    'title' => $lesson['title'], 'topic' => $lesson['topic'], 'objective' => $lesson['objective'],
                    'minutes' => $lesson['minutes'], 'icon' => $lesson['icon'],
                    'search' => mb_strtolower(implode(' ', [$summary['title'], $lesson['title'], $lesson['topic'], $lesson['objective']])),
                    'url' => route('student.lesson.show', ['lesson' => $key, ...CourseAssessments::courseQuery($course)]),
                ];
            }
            $summary['count'] = $number;
            unset($summary['lessons']);
        }
        unset($summary);
        $snapshot = [
            'available' => true, 'metrics' => null, 'student' => null, 'teacher' => null, 'catalog' => $catalog, 'courses' => $courses,
            'catalog_totals' => ['lessons' => count($catalog), 'practice_activities' => count(config('code_lab.activities')) + count(config('arduino_curriculum.practice')),
                'challenges' => collect(config('challenges'))->reject(fn (array $challenge): bool => ! empty($challenge['legacy']))->count(), 'milestones' => count(config('achievements'))],
            'rewards' => ['welcome' => Student::REGISTRATION_XP, 'correct_answer' => QuizRewards::XP_PER_ANSWER, 'perfect_quiz' => QuizRewards::PERFECT_BONUS],
            'example' => ['title' => config('lessons.output.title'), 'code' => config('lessons.output.code'), 'output' => config('lessons.output.output')],
            'year' => $now->year, 'updated_at' => $now->toIso8601String(), 'updated_label' => $now->format('M j, Y · g:i:s A').' (UTC+08:00)',
        ];
        try {
            $assignments = TeacherAssignment::where('status', 'published')->count();
            $quizzes = TeacherQuiz::where('status', 'published')->count();
            $snapshot['metrics'] = [
                'students' => Student::count(), 'educators' => TeacherAccount::where('staff_access_status', 'active')->count(), 'sections' => Classroom::count(),
                'published_activities' => $assignments + $quizzes, 'published_assignments' => $assignments, 'published_quizzes' => $quizzes,
            ];
            if ($studentId && ($student = Student::with('classroom')->find($studentId))) {
                $snapshot['student'] = [
                    'nickname' => $student->nickname, 'avatar' => $student->avatarAppearance()['emoji'],
                    'classroom' => $student->classroom?->name, 'grade' => $student->classroom?->grade,
                    'section' => $student->classroom?->section ?: $student->classroom?->period,
                    'xp' => $student->xp, 'level' => $student->level, 'level_progress' => round(($student->xp % 600) / 6, 1),
                    'xp_to_next_level' => 600 - ($student->xp % 600),
                    'badges' => StudentAchievement::where('student_id', $student->id)->count(),
                    'lessons_completed' => LessonProgress::where('student_id', $student->id)->whereIn('lesson_key', array_column($catalog, 'key'))->whereNotNull('completed_at')->count(),
                    'quizzes_submitted' => DB::table('quiz_submissions')->where('student_id', $student->id)->where('total_questions', '>', 0)->distinct()->count('teacher_quiz_id'),
                    'coding_submitted' => CodeWorkspace::where('student_id', $student->id)->whereHas('submissions')->count(),
                ];
            }
            if ($teacherId && ($teacher = TeacherAccount::find($teacherId))) {
                $roomIds = $teacher->classrooms()->pluck('id');
                $students = Student::whereIn('classroom_id', $roomIds)->pluck('id');
                $latest = LabSubmission::where('teacher_account_id', $teacher->id)->whereHas('workspace', fn ($query) => $query->whereIn('student_id', $students))
                    ->latest('id')->get(['id', 'code_workspace_id', 'reviewed_at'])->unique('code_workspace_id');
                $snapshot['teacher'] = [
                    'name' => $teacher->fullName(), 'sections' => $roomIds->count(), 'students' => $students->count(),
                    'published_assignments' => TeacherAssignment::where('teacher_account_id', $teacher->id)->where('status', 'published')->count(),
                    'published_quizzes' => TeacherQuiz::where('teacher_account_id', $teacher->id)->where('status', 'published')->count(),
                    'pending_reviews' => $latest->whereNull('reviewed_at')->count(),
                    'support_learners' => LessonProgress::whereIn('student_id', $students)->where(fn ($query) => $query->where('confidence', 'need_help')->orWhere(fn ($help) => $help->whereNotNull('help_request')->where('help_request', '!=', '')))->distinct()->count('student_id'),
                ];
            }
        } catch (QueryException) {
            // Never show fabricated zero counts or SQL/credential details during an outage.
            $snapshot['available'] = false;
            $snapshot['metrics'] = null;
            $snapshot['student'] = null;
            $snapshot['teacher'] = null;
            $snapshot['updated_at'] = null;
            $snapshot['updated_label'] = 'Live classroom data is temporarily unavailable.';
        }
        $stable = $snapshot;
        unset($stable['updated_at'],$stable['updated_label']);
        $snapshot['revision'] = hash('sha256', json_encode($stable, JSON_THROW_ON_ERROR));

        return $snapshot;
    }
}
