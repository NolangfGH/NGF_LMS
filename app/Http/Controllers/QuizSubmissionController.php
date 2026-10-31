<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\TeacherQuiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\QuizGrader;

class QuizSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $teacher = \App\Models\TeacherAccount::find(session('teacher_id'));
        if (!$teacher) return redirect()->route('teacher.login');
        $filter=$request->validate(['submission'=>['nullable','integer','min:1']]);
        $submissions = DB::table('quiz_submissions')
            ->join('teacher_quizzes', 'teacher_quizzes.id', '=', 'quiz_submissions.teacher_quiz_id')
            ->join('students', 'students.id', '=', 'quiz_submissions.student_id')
            ->where('teacher_quizzes.teacher_account_id', $teacher->id)
            ->when($filter['submission'] ?? null,fn ($q,$id) => $q->where('quiz_submissions.id',$id))
            ->select('quiz_submissions.*', 'teacher_quizzes.title', 'teacher_quizzes.questions', 'students.nickname')
            ->orderByDesc('quiz_submissions.updated_at')->get();
        return view('quiz-reviews', compact('submissions'));
    }

    private function studentFor(TeacherQuiz $quiz): Student
    {
        $student = Student::find(session('student_id'));
        abort_unless($student, 401);
        abort_unless($quiz->isAssignedTo((int) $student->classroom_id) && $quiz->status === 'published', 403);
        return $student;
    }

    public function start(TeacherQuiz $quiz, \App\Services\QuizAttemptService $attempts)
    {
        $student = $this->studentFor($quiz);
        $attempts->finalizeExpired($student);
        $attempts->start($student, $quiz);
        return redirect()->route('student.quiz');
    }

    public function draft(Request $request, TeacherQuiz $quiz, \App\Services\QuizAttemptService $attempts)
    {
        $student = $this->studentFor($quiz);
        if (\App\Models\QuizAttempt::where('student_id',$student->id)->where('teacher_quiz_id',$quiz->id)->where('sequential',true)->exists()) {
            return $this->question($request,$quiz,false);
        }
        $data = $request->validate(['answers'=>['present','array']]);
        $finished = $attempts->save($student,$quiz,$data['answers'],false);
        return response()->json(['finished'=>$finished,'saved_at'=>now()->toIso8601String()]);
    }

    public function store(Request $request, TeacherQuiz $quiz, \App\Services\QuizAttemptService $attempts)
    {
        $student = $this->studentFor($quiz);
        $request->merge(['answers'=>$request->input('answers', [])]);
        $data = $request->validate(['answers'=>['present','array']]);
        $attempts->save($student,$quiz,$data['answers'],true);
        $result = DB::table('quiz_submissions')->where('student_id',$student->id)->where('teacher_quiz_id',$quiz->id)->orderByDesc('attempt_number')->first();
        $message = $result->score === null ? 'Answers saved. This quiz needs an answer key before automatic grading.'
            : 'Answers checked! Score: '.$result->score.' / '.$result->graded_questions.'.';
        if ($result->score !== null && $result->graded_questions < $result->total_questions) $message .= ' Remaining questions need teacher review.';
        return redirect()->route('student.quiz')->with('quiz_saved',$message);
    }

    public function question(Request $request, TeacherQuiz $quiz, bool $advance = true)
    {
        $student = $this->studentFor($quiz);
        $data = $request->validate(['question_index'=>['required','integer','min:0'],'answer'=>['present'],'attempt_id'=>['nullable','integer','min:1']]);
        $result = app(\App\Services\SequentialQuizService::class)->answer($student,$quiz,$data['question_index'],$data['answer'],$advance,isset($data['attempt_id']) ? (int)$data['attempt_id'] : null);
        if ($request->expectsJson()) return response()->json($result);
        return redirect()->route('student.quiz');
    }
}
