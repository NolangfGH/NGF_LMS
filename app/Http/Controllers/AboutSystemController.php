<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AboutSystemController extends Controller
{
    private const CATEGORIES = ['experience' => 'Using the LMS', 'suggestion' => 'Suggestion', 'issue' => 'Problem or accessibility issue'];

    public function index(): Response
    {
        $actor = $this->actor();
        $entries = $actor ? $this->ownEntries($actor)->latest('id')->paginate(10) : null;

        return response()->view('about-system', ['actor' => $actor, 'entries' => $entries, 'categories' => self::CATEGORIES])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->actor();
        abort_unless($actor, 403);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'message' => ['required_without:image', 'nullable', 'string', 'max:3000'],
            'image' => ['required_without:message', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        $path = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('system-feedback', 'local');
            if (! $path) {
                throw ValidationException::withMessages(['image' => 'The image could not be saved. Please try again.']);
            }
        }
        try {
            DB::table('system_feedback')->insert([
                'student_id' => $actor instanceof Student ? $actor->id : null,
                'teacher_account_id' => $actor instanceof TeacherAccount ? $actor->id : null,
                'category' => $data['category'], 'message' => $data['message'] ?? null,
                'image_path' => $path, 'created_at' => now(),
            ]);
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return redirect()->route('system.about')->withFragment('feedback')
            ->with('feedback_notice', 'Thank you. Your feedback was sent privately to the System Admin.');
    }

    public function inbox(Request $request): Response
    {
        $teacher = $this->actor();
        abort_unless($teacher instanceof TeacherAccount && $teacher->isSchoolAdmin(), 403);
        $filter = $request->validate(['category' => ['nullable', Rule::in(array_keys(self::CATEGORIES))]]);
        $entries = DB::table('system_feedback as feedback')
            ->leftJoin('teacher_accounts as staff', 'staff.id', '=', 'feedback.teacher_account_id')
            ->leftJoin('students', 'students.id', '=', 'feedback.student_id')
            ->when($filter['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('feedback.category', $category))
            ->orderByDesc('feedback.id')->paginate(20, [
                'feedback.*', 'staff.first_name', 'staff.last_name', 'students.nickname',
            ])->withQueryString();

        return response()->view('system-feedback', ['pageTitle' => 'System feedback', 'teacher' => $teacher, 'entries' => $entries, 'categories' => self::CATEGORIES])
            ->header('Cache-Control', 'private, no-store');
    }

    public function image(int $feedback): StreamedResponse
    {
        $actor = $this->actor();
        abort_unless($actor, 403);
        $query = $actor instanceof TeacherAccount && $actor->isSchoolAdmin() ? DB::table('system_feedback') : $this->ownEntries($actor);
        $path = $query->where('id', $feedback)->value('image_path');
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, headers: ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function actor(): Student|TeacherAccount|null
    {
        if (session()->has('teacher_id')) {
            $staff = TeacherAccount::find(session('teacher_id'));
            if ($staff?->hasStaffAccess()) {
                return $staff;
            }
        }

        return session()->has('student_id') ? Student::find(session('student_id')) : null;
    }

    private function ownEntries(Student|TeacherAccount $actor): Builder
    {
        return DB::table('system_feedback')->where($actor instanceof Student ? 'student_id' : 'teacher_account_id', $actor->id);
    }
}
