<?php

namespace App\Http\Controllers;

use App\Models\TeacherAccount;
use Illuminate\Http\Request;

class StaffRegistrationStatusController extends Controller
{
    public function show(Request $request)
    {
        $account = TeacherAccount::find($request->session()->get('pending_staff_registration_id'));
        if (! $account) {
            return redirect()->route('teacher.login');
        }

        return response()->view('staff-registration-status', compact('account'))->header('Cache-Control', 'private, no-store');
    }

    public function check(Request $request)
    {
        $account = TeacherAccount::find($request->session()->get('pending_staff_registration_id'));
        abort_unless($account, 401, 'Sign in to check your staff access request.');

        return response()->json(['status' => $account->staff_access_status, 'role' => $account->roleLabel(), 'note' => $account->staff_review_note])->header('Cache-Control', 'private, no-store');
    }
}
