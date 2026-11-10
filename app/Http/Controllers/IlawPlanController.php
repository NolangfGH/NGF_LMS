<?php

namespace App\Http\Controllers;

use App\Models\LessonContent;
use App\Models\TeacherAccount;
use App\Services\IlawPlanTemplate;
use App\Services\LearningNotifications;
use App\Services\LessonService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IlawPlanController extends Controller
{
    public function edit(string $key): Response
    {
        return response()->view('ilaw.edit', $this->teacherPlan($key))->header('Cache-Control', 'private, no-store');
    }

    public function download(string $key): Response
    {
        return response()->view('ilaw.download', $this->teacherPlan($key), 200, [
            'Content-Disposition' => 'attachment; filename="ilaw-'.$key.'.html"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function save(Request $request, string $key): RedirectResponse
    {
        ['teacher' => $teacher] = $this->teacherPlan($key);
        $rules = [
            'action' => ['required', Rule::in(['draft', 'submit'])],
            'revision' => ['required', 'integer', 'min:0'],
        ];
        foreach (IlawPlanTemplate::GROUPS as $fields) {
            foreach (array_keys($fields) as $field) {
                $required = in_array($field, ['title', 'learning_area', 'grade_section', 'sessions', 'intentions', 'learning_experience', 'assessing_learning', 'ways_forward'], true);
                $rules[$field] = [
                    $required ? 'required_if:action,submit' : 'nullable',
                    ...match ($field) {
                        'sessions' => ['integer', 'between:1,100'],
                        'title', 'learning_area', 'grade_section' => ['string', 'max:200'],
                        default => ['string', 'max:6000'],
                    },
                ];
            }
        }
        $data = $request->validate($rules);
        $revision = (int) $data['revision'];
        $submit = $data['action'] === 'submit';
        unset($data['action'], $data['revision']);
        $attributes = [
            'content' => json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
            'revision' => $revision + 1,
            'status' => $submit ? 'submitted' : 'draft',
            'submitted_at' => $submit ? now() : null,
            'updated_at' => now(),
        ];
        $saved = $revision === 0
            ? DB::table('ilaw_lesson_plans')->insertOrIgnore($attributes + [
                'teacher_account_id' => $teacher->id, 'lesson_key' => $key, 'created_at' => now(),
            ])
            : $this->ownedPlan($teacher, $key)->where('revision', $revision)->update($attributes);
        abort_unless($saved === 1, 409, 'This plan changed in another tab. Reload it before saving.');

        return redirect()->route('teacher.ilaw.edit', $key)->with('lesson_notice', $submit
            ? 'Lesson plan sent to your school principal for review.'
            : 'Private lesson plan draft saved.');
    }

    public function reviews(): Response
    {
        $teacher = $this->principal();
        $plans = $this->schoolPlans($teacher)->orderByRaw("plans.status = 'submitted' DESC")
            ->orderByDesc('plans.submitted_at')->orderByDesc('plans.id')
            ->paginate(20, ['plans.id', 'plans.lesson_key', 'plans.content', 'plans.status', 'plans.submitted_at', 'owner.first_name', 'owner.last_name']);

        return response()->view('ilaw.reviews', ['pageTitle' => 'ILAW plan reviews', 'teacher' => $teacher, 'plans' => $plans])
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(int $plan): Response
    {
        $teacher = $this->principal();
        $record = $this->schoolPlans($teacher)->where('plans.id', $plan)
            ->first(['plans.*', 'owner.first_name', 'owner.last_name']);
        abort_unless($record, 404);

        return response()->view('ilaw.review', [
            'pageTitle' => 'Review ILAW plan', 'teacher' => $teacher, 'plan' => $record,
            'content' => json_decode($record->content, true, flags: JSON_THROW_ON_ERROR),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function evaluate(Request $request, int $plan, LearningNotifications $notifications): RedirectResponse
    {
        $principal = $this->principal();
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:3000'],
        ]);
        DB::transaction(function () use ($principal, $plan, $data, $notifications): void {
            $record = $this->schoolPlans($principal)->where('plans.id', $plan)->lockForUpdate()->first(['plans.*']);
            abort_unless($record, 404);
            abort_unless($record->status === 'submitted' && (int) $record->revision === (int) $data['revision'], 409, 'This plan changed or has already been evaluated. Reload it before reviewing.');
            DB::table('ilaw_lesson_plans')->where('id', $plan)->update([
                'status' => 'evaluated', 'evaluated_by' => $principal->id, 'evaluated_at' => now(),
                'review_comment' => $data['comment'] ?? null, 'revision' => $record->revision + 1, 'updated_at' => now(),
            ]);
            $notifications->ilawPlanEvaluated($record->teacher_account_id, $plan, $record->revision);
        }, 3);

        return redirect()->route('school.ilaw.show', $plan)->with('lesson_notice', 'Plan marked evaluated. The teacher has been notified.');
    }

    private function teacherPlan(string $key): array
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && ! $teacher->isPrincipal(), 403);
        $plan = $this->ownedPlan($teacher, $key)->first();
        $content = $plan ? json_decode($plan->content, true, flags: JSON_THROW_ON_ERROR) : null;
        $lesson = LessonContent::where('teacher_account_id', $teacher->id)->where('lesson_key', $key)->first()?->definition
            ?? app(LessonService::class)->catalogForTeacher($teacher->id, null)[$key] ?? null;
        abort_unless($lesson || $plan, 404);

        return [
            'pageTitle' => 'ILAW lesson plan', 'teacher' => $teacher, 'key' => $key, 'plan' => $plan,
            'lesson' => $lesson ?? ['title' => $content['title'] ?? $key, 'topic' => 'Saved lesson plan'],
            'content' => $content ?? IlawPlanTemplate::defaults($lesson),
        ];
    }

    private function ownedPlan(TeacherAccount $teacher, string $key): Builder
    {
        return DB::table('ilaw_lesson_plans')->where('teacher_account_id', $teacher->id)->where('lesson_key', $key);
    }

    private function principal(): TeacherAccount
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher?->hasStaffAccess() && $teacher->isPrincipal(), 403);

        return $teacher;
    }

    private function schoolPlans(TeacherAccount $principal): Builder
    {
        return DB::table('ilaw_lesson_plans as plans')
            ->join('teacher_accounts as owner', 'owner.id', '=', 'plans.teacher_account_id')
            ->where('owner.school', $principal->school)
            ->where('owner.staff_access_status', 'active')
            ->whereIn('owner.role', ['teacher', 'school_admin'])
            ->whereIn('plans.status', ['submitted', 'evaluated']);
    }
}
