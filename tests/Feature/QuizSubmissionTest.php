<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizSubmissionTest extends TestCase
{
    use RefreshDatabase, \Tests\Supports\ExistingLegacyQuizAttempt;

    public function test_one_quiz_can_target_three_sections_and_targets_can_be_updated(): void
    {
        $teacher = TeacherAccount::create(['first_name'=>'Test','last_name'=>'Teacher','email'=>'multi@example.test','school'=>'School','password'=>'password123']);
        $outsider = TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','email'=>'outside@example.test','school'=>'School','password'=>'password123']);
        $rooms = collect(['A','B','C','D'])->map(fn ($section) => Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','section'=>$section,'join_code'=>'MULTI'.$section]));
        $foreign = Classroom::create(['teacher_account_id'=>$outsider->id,'name'=>'C++','join_code'=>'FOREIGN']);
        $students = $rooms->map(fn ($room) => Student::create(['classroom_id'=>$room->id,'first_name'=>'Student','nickname'=>'Cadet '.$room->section,'student_id'=>'MULTI'.$room->id,'password'=>'password123']));
        $payload = ['classroom_ids'=>$rooms->take(3)->pluck('id')->all(),'title'=>'Shared quiz','status'=>'published','xp'=>50,'time_limit'=>15,'questions'=>[
            ['type'=>'identification','prompt'=>'Loop keyword?', 'options'=>[], 'correct'=>['while']],
        ]];
        $this->withSession(['teacher_id'=>$teacher->id])->postJson(route('teacher.quizzes.store'),$payload)->assertOk();
        $quiz = TeacherQuiz::first();
        $this->assertSame(3, $quiz->classrooms()->count());
        $this->assertDatabaseCount('teacher_quizzes',1);
        foreach ($students->take(3) as $student) {
            $this->withSession(['student_id'=>$student->id])->get(route('student.quiz'))->assertOk()->assertSee('Shared quiz');
            $this->beginLegacyQuiz($quiz)->assertRedirect();
            $this->post(route('student.quiz.submit',$quiz),['answers'=>['while']])->assertRedirect();
        }
        $this->assertDatabaseCount('quiz_submissions',3);
        $this->withSession(['student_id'=>$students[3]->id])->get(route('student.quiz'))->assertDontSee('Shared quiz');
        $this->post(route('student.quiz.submit',$quiz),['answers'=>['while']])->assertForbidden();
        $payload['classroom_ids'] = [$rooms[1]->id,$rooms[2]->id,$rooms[3]->id];
        $this->putJson(route('teacher.quizzes.update',$quiz),$payload)->assertOk();
        $this->withSession(['student_id'=>$students[0]->id])->get(route('student.quiz'))->assertDontSee('Shared quiz');
        $this->post(route('student.quiz.submit',$quiz),['answers'=>['while']])->assertForbidden();
        $this->withSession(['student_id'=>$students[3]->id])->get(route('student.quiz'))->assertSee('Shared quiz');
        $this->get(route('teacher.quizzes'))->assertOk()->assertSee('Select all sections')->assertSee('classroom_ids');
        $payload['classroom_ids'][] = $foreign->id;
        $this->putJson(route('teacher.quizzes.update',$quiz),$payload)->assertUnprocessable()->assertJsonValidationErrors('classroom_ids.3');
        $payload['classroom_ids'] = [];
        $this->putJson(route('teacher.quizzes.update',$quiz),$payload)->assertUnprocessable();
        $this->assertSame(3, $quiz->classrooms()->count());
    }

    public function test_teacher_can_edit_update_and_delete_only_their_own_quizzes(): void
    {
        $teacher = TeacherAccount::create(['first_name'=>'Test','last_name'=>'Teacher','email'=>'edit@example.test','school'=>'School','password'=>'password123']);
        $other = TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','email'=>'editother@example.test','school'=>'School','password'=>'password123']);
        $class = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','join_code'=>'EDIT12']);
        $student = Student::create(['classroom_id'=>$class->id,'first_name'=>'Student','nickname'=>'Cadet','student_id'=>'EDIT123','password'=>'password123']);
        $questions = [
            ['type'=>'multiple_choice','prompt'=>'Output?', 'options'=>['cout','cin'], 'correct'=>['cout']],
            ['type'=>'identification','prompt'=>'Loop?', 'options'=>[], 'correct'=>['while']],
            ['type'=>'true_false','prompt'=>'Compiled?', 'options'=>['True','False'], 'correct'=>['True']],
            ['type'=>'multiple_answer','prompt'=>'Loops?', 'options'=>['for','while','if'], 'correct'=>['for','while']],
        ];
        $quiz = TeacherQuiz::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$class->id,'title'=>'Original','questions'=>$questions,'status'=>'published']);
        $payload = ['classroom_id'=>$class->id,'title'=>'Updated quiz','instructions'=>'Updated instructions','status'=>'draft','xp'=>70,'time_limit'=>20,'opens_at'=>'2026-11-01T09:00','closes_at'=>'2026-11-01T10:00','questions'=>$questions];
        $this->withSession(['teacher_id'=>$other->id])->putJson(route('teacher.quizzes.update',$quiz),$payload)->assertForbidden();
        $this->deleteJson(route('teacher.quizzes.destroy',$quiz))->assertForbidden();
        $this->assertSame('Original', $quiz->fresh()->title);
        $this->withSession(['teacher_id'=>$teacher->id])->putJson(route('teacher.quizzes.update',$quiz),$payload)->assertOk();
        $this->assertDatabaseCount('teacher_quizzes',1);
        $this->assertSame('Updated quiz', $quiz->fresh()->title);
        $this->assertSame('draft', $quiz->fresh()->status);
        $this->assertSame($questions, $quiz->fresh()->questions);
        $this->assertSame('2026-11-01 01:00:00', $quiz->fresh()->opens_at->format('Y-m-d H:i:s'));
        $this->withSession(['student_id'=>$student->id])->get(route('student.quiz'))->assertDontSee('Updated quiz');
        $payload['status'] = 'published';
        $payload['opens_at'] = null;
        $payload['closes_at'] = null;
        $this->putJson(route('teacher.quizzes.update',$quiz),$payload)->assertOk();
        $this->beginLegacyQuiz($quiz)->assertRedirect();
        $this->post(route('student.quiz.submit',$quiz),['answers'=>['cout','while','True',['while','for']]])->assertRedirect();
        $payload['questions'][1]['correct'] = ['for'];
        $this->putJson(route('teacher.quizzes.update',$quiz),$payload)->assertOk();
        $this->assertDatabaseHas('quiz_submissions',['teacher_quiz_id'=>$quiz->id,'score'=>4]);
        $this->get(route('teacher.quizzes'))->assertOk()->assertSee('Edit Quiz')->assertSee('Delete')->assertSee('Updated quiz');
        $this->deleteJson(route('teacher.quizzes.destroy',$quiz))->assertOk();
        $this->assertSoftDeleted('teacher_quizzes',['id'=>$quiz->id]);
        $this->assertDatabaseCount('quiz_submissions',1);
        $this->get(route('student.quiz'))->assertDontSee('Updated quiz');
        $this->post(route('student.quiz.submit',$quiz),['answers'=>['cout','while','True',['while','for']]])->assertNotFound();
        $this->get(route('teacher.quiz.reviews'))->assertOk()->assertSee('Updated quiz');
        $this->withSession(['teacher_id'=>null])->putJson(route('teacher.quizzes.update', $quiz->id),$payload)->assertNotFound();
    }

    public function test_section_quiz_schedule_and_submission_window_are_enforced(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 00:30:00', 'UTC'));
        $teacher = TeacherAccount::create(['first_name'=>'Test','last_name'=>'Teacher','email'=>'schedule@example.test','school'=>'School','password'=>'password123']);
        $class = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','join_code'=>'SCHED1','section'=>'Section A']);
        $other = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','join_code'=>'SCHED2','section'=>'Section B']);
        $student = Student::create(['classroom_id'=>$class->id,'first_name'=>'Student','nickname'=>'Cadet','student_id'=>'SCHED123','password'=>'password123']);
        $payload = ['classroom_id'=>$class->id,'title'=>'Scheduled quiz','status'=>'published','xp'=>50,'time_limit'=>15,'opens_at'=>'2026-10-28T09:00','closes_at'=>'2026-10-28T10:00','questions'=>[
            ['type'=>'identification','prompt'=>'Scheduled secret question','options'=>[],'correct'=>['while']],
        ]];
        $this->withSession(['teacher_id'=>$teacher->id])->postJson(route('teacher.quizzes.store'), $payload)->assertOk();
        $quiz = TeacherQuiz::first();
        $this->assertSame('2026-10-28 01:00:00', $quiz->opens_at->format('Y-m-d H:i:s'));
        $this->assertSame($class->id, $quiz->classroom_id);
        $this->withSession(['student_id'=>$student->id])->get(route('student.quiz'))->assertOk()->assertSee('9:00 AM')->assertDontSee('Scheduled secret question');
        $this->postJson(route('student.quiz.submit',$quiz), ['answers'=>['while']])->assertUnprocessable();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 01:00:00', 'UTC'));
        $this->beginLegacyQuiz($quiz)->assertRedirect();
        $this->get(route('student.quiz'))->assertSee('Scheduled secret question');
        $this->postJson(route('student.quiz.submit',$quiz), ['answers'=>['while']])->assertRedirect();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 02:00:00', 'UTC'));
        $this->postJson(route('student.quiz.submit',$quiz), ['answers'=>['while']])->assertUnprocessable();
        $this->assertDatabaseCount('quiz_submissions', 1);
        $student->update(['classroom_id'=>$other->id]);
        $this->get(route('student.quiz'))->assertDontSee('Scheduled quiz');
        $payload['closes_at'] = '2026-10-28T08:00';
        $this->postJson(route('teacher.quizzes.store'), $payload)->assertUnprocessable();
        $this->travelBack();
    }

    public function test_sections_preserve_existing_enrollment_and_cannot_target_another_teacher(): void
    {
        $teacher = TeacherAccount::create(['first_name'=>'Test','last_name'=>'Teacher','email'=>'section@example.test','school'=>'School','password'=>'password123']);
        $otherTeacher = TeacherAccount::create(['first_name'=>'Other','last_name'=>'Teacher','email'=>'other@example.test','school'=>'School','password'=>'password123']);
        $class = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','join_code'=>'SEC123']);
        $foreign = Classroom::create(['teacher_account_id'=>$otherTeacher->id,'name'=>'C++','join_code'=>'SEC456']);
        $this->withSession(['teacher_id'=>$teacher->id])->post(route('teacher.quiz.sections.store'), ['classroom_id'=>$class->id,'section'=>'Section A'])->assertRedirect();
        $this->assertSame('Section A', $class->fresh()->section);
        $this->assertSame('SEC123', $class->fresh()->join_code);
        $this->post(route('teacher.quiz.sections.store'), ['classroom_id'=>$class->id,'section'=>'Section B'])->assertRedirect();
        $this->assertSame(2, $teacher->classrooms()->count());
        $this->get(route('teacher.quizzes'))->assertOk()->assertSee('Section A')->assertSee('Section B');
        $this->postJson(route('teacher.quizzes.store'), ['classroom_id'=>$foreign->id])->assertUnprocessable()->assertJsonValidationErrors('classroom_id');
    }

    public function test_all_four_types_are_graded_and_results_survive_reload(): void
    {
        $teacher = TeacherAccount::create(['first_name'=>'Test','last_name'=>'Teacher','email'=>'grader@example.test','school'=>'School','password'=>'password123']);
        $class = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','join_code'=>'GRADE1']);
        $student = Student::create(['classroom_id'=>$class->id,'first_name'=>'Student','nickname'=>'Cadet','student_id'=>'GRADE123','password'=>'password123']);
        $quiz = TeacherQuiz::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$class->id,'title'=>'Four types','status'=>'published','questions'=>[
            ['type'=>'multiple_choice','prompt'=>'Output?', 'options'=>['cout','cin'], 'correct'=>['cout']],
            ['type'=>'identification','prompt'=>'Loop?', 'options'=>[], 'correct'=>['while loop']],
            ['type'=>'true_false','prompt'=>'C++ is compiled?', 'options'=>['True','False'], 'correct'=>['True']],
            ['type'=>'multiple_answer','prompt'=>'Loops?', 'options'=>['while','for','if','switch'], 'correct'=>['while','for']],
        ]]);
        $this->withSession(['student_id'=>$student->id])->beginLegacyQuiz($quiz)->assertRedirect();
        $response = $this->post(route('student.quiz.submit', $quiz), ['answers'=>['cout','  WHILE   loop  ','True',['for','while']], 'score'=>999]);
        $response->assertRedirect()->assertSessionHas('quiz_saved', 'Answers checked! Score: 4 / 4.');
        $this->assertDatabaseHas('quiz_submissions', ['student_id'=>$student->id,'score'=>4,'graded_questions'=>4,'total_questions'=>4]);
        $this->get(route('student.quiz'))->assertOk()->assertSee('Saved score: 4 / 4');
        $this->withSession(['student_id'=>$student->id])->post(route('student.quiz.submit', $quiz), ['answers'=>['cin','while','False',['while','if']]])->assertRedirect()->assertSessionHasErrors('quiz');
        $this->assertDatabaseCount('quiz_submissions',1);
        $this->assertDatabaseHas('quiz_submissions',['score'=>4]);
        $this->withSession(['teacher_id'=>$teacher->id])->get(route('teacher.quiz.reviews'))->assertOk()->assertSee('Correct')->assertSee('while, for');
    }

    public function test_teacher_cannot_publish_choices_with_an_invalid_answer_key(): void
    {
        $teacher = TeacherAccount::create(['first_name'=>'Test','last_name'=>'Teacher','email'=>'key@example.test','school'=>'School','password'=>'password123']);
        Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','join_code'=>'KEY123']);
        $payload = ['title'=>'Keys','status'=>'published','xp'=>50,'time_limit'=>15,'questions'=>[
            ['type'=>'multiple_choice','prompt'=>'Output?', 'options'=>['cout','cin'], 'correct'=>['invalid']],
        ]];
        $this->withSession(['teacher_id'=>$teacher->id])->postJson(route('teacher.quizzes.store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('teacher_quizzes',0);
        $payload['questions'][0]['correct'] = ['cout'];
        $this->postJson(route('teacher.quizzes.store'), $payload)->assertOk();
        $this->assertSame(['cout'], TeacherQuiz::first()->questions[0]['correct']);
    }

    public function test_answers_are_persisted_and_other_classes_cannot_submit(): void
    {
        $teacher = TeacherAccount::create(['first_name'=>'Test','last_name'=>'Teacher','email'=>'teacher@example.test','school'=>'School','password'=>'password123']);
        $class = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'C++','join_code'=>'TEST12']);
        $other = Classroom::create(['teacher_account_id'=>$teacher->id,'name'=>'Other','join_code'=>'OTHER1']);
        $student = Student::create(['classroom_id'=>$class->id,'first_name'=>'Student','nickname'=>'Cadet','student_id'=>'TEST123','password'=>'password123']);
        $quiz = TeacherQuiz::create(['teacher_account_id'=>$teacher->id,'classroom_id'=>$class->id,'title'=>'Loops','questions'=>[['prompt'=>'Loop keyword?']],'status'=>'published']);
        $this->withSession(['student_id'=>$student->id])->beginLegacyQuiz($quiz)->assertRedirect();
        $this->withSession(['student_id'=>$student->id])->post(route('student.quiz.submit',$quiz),['answers'=>['while']])->assertRedirect();
        $this->assertDatabaseCount('quiz_submissions',1);
        $this->withSession(['student_id'=>$student->id])->post(route('student.quiz.submit',$quiz),['answers'=>['for']])->assertRedirect();
        $this->assertDatabaseCount('quiz_submissions',1);
        $quiz->update(['classroom_id'=>$other->id]);
        $this->withSession(['student_id'=>$student->id])->post(route('student.quiz.submit',$quiz),['answers'=>['while']])->assertForbidden();
    }
}
