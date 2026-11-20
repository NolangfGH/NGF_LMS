<?php

namespace App\Http\Controllers;

use App\Models\QuizBeeMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use App\Services\ExamItemAnalysis;
use App\Services\LearningAnalytics;
use App\Services\LearningCourses;
use App\Services\LessonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SystemAnalyticsController extends Controller
{
    public function index(Request $request, ExamItemAnalysis $analysis, LearningAnalytics $analytics): Response|RedirectResponse
    {
        $teacher = TeacherAccount::find($request->session()->get('teacher_id'));
        if (! $teacher) {
            return redirect()->route('teacher.login');
        }
        abort_unless($teacher->hasStaffAccess() && ! $teacher->isPrincipal(), 403);
        $filters = $request->validate([
            'classroom' => ['nullable', 'integer', 'min:1'],
            'term' => ['nullable', Rule::in(['Term1', 'Term2', 'Term3'])],
            'exam' => ['nullable', 'integer', 'min:1'],
            'version' => ['nullable', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'kind' => ['nullable', Rule::in(array_keys(LearningAnalytics::KINDS))],
            'course' => ['nullable', Rule::in(LearningCourses::keys())],
            'lesson' => ['nullable', 'string', 'max:60'], 'bee' => ['nullable', 'integer', 'min:1'],
            'attempt' => ['nullable', Rule::in(['first', 'latest'])],
        ]);
        $kind = $filters['kind'] ?? 'quiz';
        $course = $filters['course'] ?? 'cpp';
        $quizKind = in_array($kind, ['quiz', 'summative-1', 'summative-2', 'term-exam'], true);
        $classrooms = $teacher->classrooms()->orderBy('grade')->orderBy('section')->get(['id', 'name', 'grade', 'section']);
        $selectedRoom = isset($filters['classroom']) ? $classrooms->firstWhere('id', (int) $filters['classroom']) : null;
        abort_if(isset($filters['classroom']) && ! $selectedRoom, 404);
        $scope = $selectedRoom ? collect([$selectedRoom->id]) : $classrooms->pluck('id');

        $terms = DB::table('grading_assessments as assessment')
            ->join('grading_sheets as sheet', 'sheet.id', '=', 'assessment.grading_sheet_id')
            ->where('sheet.teacher_account_id', $teacher->id)->whereIn('sheet.classroom_id', $scope)
            ->where('assessment.source_type', 'quiz')->get(['assessment.source_id', 'sheet.term'])->groupBy('source_id');
        $exams = TeacherQuiz::where('teacher_account_id', $teacher->id)->where('status', 'published')
            ->where(fn ($query) => $query->whereIn('classroom_id', $scope)
                ->orWhereHas('classrooms', fn ($rooms) => $rooms->whereIn('classrooms.id', $scope)))
            ->when($quizKind && isset($filters['course']), fn ($query) => $query->where('course', $course))
            ->with('classrooms:id')->latest('id')->get(['id', 'classroom_id', 'title', 'course']);
        $exams->each(function (TeacherQuiz $exam) use ($terms): void {
            $linked = $terms->get($exam->id, collect())->pluck('term')->unique()->sort()->values()->all();
            if ($linked === [] && preg_match('/\bterm[\s_-]*([123])\b/i', $exam->title, $matches)) {
                $linked = ['Term'.$matches[1]];
            }
            $exam->setAttribute('report_terms', $linked);
        });
        if (isset($filters['term'])) {
            $exams = $exams->filter(fn (TeacherQuiz $exam): bool => in_array($filters['term'], $exam->report_terms, true));
        }
        if ($kind !== 'quiz') {
            $exams = $exams->filter(static function (TeacherQuiz $exam) use ($kind): bool {
                return match ($kind) {
                    'summative-1' => (bool) preg_match('/\b(?:summative\s*(?:test|exam)?\s*1|(?:1st|first)\s+summative)\b/i', $exam->title),
                    'summative-2' => (bool) preg_match('/\b(?:summative\s*(?:test|exam)?\s*2|(?:2nd|second)\s+summative)\b/i', $exam->title),
                    'term-exam' => (bool) preg_match('/\bterm\s*(?:[123]\s*)?exam\b/i', $exam->title),
                    default => false,
                };
            });
        }

        $exam = $quizKind && isset($filters['exam']) ? $exams->firstWhere('id', (int) $filters['exam']) : null;
        abort_if($quizKind && isset($filters['exam']) && ! $exam, 404);
        $report = null;
        if ($exam) {
            $targets = $exam->classrooms->pluck('id')->push($exam->classroom_id);
            $exam->questions = TeacherQuiz::where('teacher_account_id', $teacher->id)->where('status', 'published')->findOrFail($exam->id)->questions;
            $report = $analysis->report($exam, $scope->intersect($targets)->values(), $filters['version'] ?? null);
        }

        $lessons = collect(app(LessonService::class)->catalogForTeacher($teacher->id, $course))
            ->filter(fn (array $lesson): bool => ! isset($filters['term']) || ($lesson['term'] ?? null) === $filters['term']);
        $selectedLesson = isset($filters['lesson']) ? $lessons->get($filters['lesson']) : null;
        abort_if($kind === 'checkpoint' && isset($filters['lesson']) && ! $selectedLesson, 404);
        $bees = QuizBeeMatch::where('teacher_account_id', $teacher->id)->where('course', $course)->where('status', 'completed')
            ->where('category', $kind === 'bee-group' ? 'group' : 'individual')
            ->whereIn('id', DB::table('quiz_bee_members')->whereIn('student_id', Student::whereIn('classroom_id', $scope)->select('id'))->select('quiz_bee_match_id'))
            ->latest('id')->get();
        $selectedBee = isset($filters['bee']) ? $bees->firstWhere('id', (int) $filters['bee']) : null;
        abort_if(str_starts_with($kind, 'bee-') && isset($filters['bee']) && ! $selectedBee, 404);
        $insightTitle = LearningAnalytics::KINDS[$kind];
        if (in_array($kind, ['pre', 'post'], true)) {
            $insightTitle = LearningCourses::title($course).' · '.$insightTitle;
        }
        $insight = null;
        if ($report) {
            $insightTitle = $exam->title;
            $insight = ['metrics' => $report['metrics'], 'items' => [], 'skills' => [], 'least_mastered' => $report['least_mastered'], 'versions' => [], 'missing_items' => 0,
                'unit' => 'Learners', 'basis' => 'First saved, fully graded submission per learner for the selected question version.'];
        } elseif (in_array($kind, ['reasoning', 'pre', 'post'], true)) {
            $insight = $analytics->courseResults($scope, $kind, $course, $filters['version'] ?? null, $filters['attempt'] ?? 'first');
        } elseif ($kind === 'checkpoint' && $selectedLesson) {
            $insightTitle = $selectedLesson['title'];
            $insight = $analytics->checkpointResults($scope, $filters['lesson'], $selectedLesson, $filters['version'] ?? null);
        } elseif (str_starts_with($kind, 'bee-') && $selectedBee) {
            $insightTitle = $selectedBee->title;
            $insight = $analytics->beeResults($selectedBee, $scope, $filters['version'] ?? null);
        }

        return response()->view('system-analytics', compact('teacher', 'classrooms', 'selectedRoom', 'filters', 'exams', 'exam', 'report', 'kind', 'course', 'quizKind', 'lessons', 'selectedLesson', 'bees', 'selectedBee', 'insight', 'insightTitle'))
            ->header('Cache-Control', 'private, no-store');
    }
}
