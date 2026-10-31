<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\TeacherAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffAccessService
{
    public function register(array $data): TeacherAccount
    {
        return DB::transaction(function () use ($data): TeacherAccount {
            $role = $data['requested_role'];
            $account = new TeacherAccount(Arr::only($data, ['first_name', 'middle_initial', 'last_name', 'email', 'school', 'password']));
            $account->forceFill(['role' => 'teacher', 'requested_role' => $role, 'staff_access_status' => $role === 'teacher' ? 'active' : 'pending'])->save();
            if ($role === 'teacher') {
                $code = strtoupper(trim($data['class_code'] ?? ''));
                if ($code === '') {
                    do {
                        $code = Str::upper(Str::random(8));
                    } while (Classroom::where('join_code', $code)->exists());
                }
                Classroom::create(['teacher_account_id' => $account->id, 'name' => $data['class_name'], 'grade' => $data['grade'],
                    'section' => $data['section'], 'period' => $data['period'] ?? '', 'join_code' => $code]);
            }

            return $account;
        }, 3);
    }

    public function review(TeacherAccount $admin, TeacherAccount $account, array $data): void
    {
        DB::transaction(function () use ($admin, $account, $data): void {
            TeacherAccount::where('role', 'school_admin')->where('staff_access_status', 'active')->orderBy('id')->lockForUpdate()->get();
            $actor = TeacherAccount::whereKey($admin->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->hasStaffAccess() && $actor->isSchoolAdmin(), 403);
            if (! Hash::check($data['current_password'], $actor->password)) {
                throw ValidationException::withMessages(['current_password' => 'Confirm your own current password.']);
            }
            abort_if($account->id === $actor->id, 422, 'Ask another System Admin to change your own role.');
            $target = TeacherAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            abort_if($target->staff_revision !== (int) $data['revision'], 409, 'This account was reviewed in another tab. Refresh before making another decision.');
            $decision = $data['decision'] ?? 'approved';
            abort_if($decision === 'rejected' && $target->staff_access_status !== 'pending', 409, 'Only pending requests can be declined.');
            $role = $decision === 'approved' ? $data['role'] : $target->role;
            abort_if($decision === 'approved' && $role === 'principal' && $target->classrooms()->exists(), 422, 'Archive or reassign this account’s teaching classes before granting read-only Principal access.');
            $prior = $target->role;
            $target->forceFill(['role' => $role, 'staff_access_status' => $decision === 'approved' ? 'active' : 'rejected',
                'staff_reviewed_by' => $actor->id, 'staff_reviewed_at' => now(), 'staff_review_note' => $data['note'] ?? null,
                'staff_revision' => $target->staff_revision + 1, 'session_version' => $target->session_version + 1])->save();
            DB::table('staff_role_reviews')->insert(['account_id' => $target->id, 'reviewer_id' => $actor->id, 'previous_role' => $prior,
                'assigned_role' => $role, 'decision' => $decision, 'note' => $data['note'] ?? null, 'created_at' => now()]);
        }, 3);
    }

    public function bootstrapAdmin(TeacherAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            $current = TeacherAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $prior = $current->role;
            $current->forceFill(['role' => 'school_admin', 'staff_access_status' => 'active', 'staff_reviewed_at' => now(),
                'staff_revision' => $current->staff_revision + 1, 'session_version' => $current->session_version + 1])->save();
            DB::table('staff_role_reviews')->insert(['account_id' => $current->id, 'reviewer_id' => null, 'previous_role' => $prior,
                'assigned_role' => 'school_admin', 'decision' => 'approved', 'note' => 'Trusted account designated through the server administrator command.', 'created_at' => now()]);
        }, 3);
    }
}
