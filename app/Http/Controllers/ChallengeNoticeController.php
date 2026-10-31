<?php

namespace App\Http\Controllers;

use App\Models\ChallengeNotice;
use App\Models\CodingMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Http\Request;

class ChallengeNoticeController extends Controller
{
    private function actor(Request $request): array
    {
        $teacher = $request->routeIs('teacher.*');
        $id = $teacher ? TeacherAccount::find(session('teacher_id'))?->id : Student::find(session('student_id'))?->id;
        abort_unless($id, 401);

        return [$teacher ? 'teacher_account_id' : 'student_id', $id, $teacher ? 'teacher' : 'student'];
    }

    public function index(Request $request)
    {
        [$field,$id,$role] = $this->actor($request);
        $query = ChallengeNotice::where($field, $id)->whereNotNull('coding_match_id');
        $pending = $role === 'student' ? CodingMatch::where('status', 'inviting')->whereHas('participants', fn ($q) => $q->where('student_id', $id)->where('response', 'invited'))->count() : 0;

        return response()->json(['unread' => (clone $query)->whereNull('read_at')->count(), 'invitations' => $pending,
            'items' => $query->latest('id')->limit(12)->get()->map(fn ($notice) => [
                'id' => $notice->id, 'message' => $notice->message, 'unread' => $notice->read_at === null,
                'time' => $notice->created_at->toIso8601String(), 'url' => route($role.'.matches.show', $notice->coding_match_id),
            ]),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function read(Request $request)
    {
        [$field,$id] = $this->actor($request);
        $data = $request->validate(['through' => ['required', 'integer', 'min:1']]);
        ChallengeNotice::where($field, $id)->whereNotNull('coding_match_id')->where('id', '<=', $data['through'])->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifications marked as read.']);
    }
}
