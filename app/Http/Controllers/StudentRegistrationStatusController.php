<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

class StudentRegistrationStatusController extends Controller
{
    private function record(Request $request): ?Student
    {
        return $request->session()->has('pending_registration_id')
            ? Student::withUnapproved()->with('classroom')->find($request->session()->get('pending_registration_id')) : null;
    }

    public function show(Request $request)
    {
        $student = $this->record($request);
        if (! $student) {
            return redirect()->route('student.login');
        }

        return response()->view('student-registration-status', ['registration' => $student])->header('Cache-Control', 'private, no-store');
    }

    public function check(Request $request)
    {
        $student = $this->record($request);
        abort_unless($student, 401, 'Sign in to check your registration status.');

        return response()->json(['status' => $student->registration_status, 'note' => $student->registration_review_note,
            'reviewed_at' => $student->registration_reviewed_at?->timezone('Asia/Singapore')->format('M j, Y · g:i A'),
            'archived' => (bool) $student->classroom?->archived_at])->header('Cache-Control', 'private, no-store');
    }
}
