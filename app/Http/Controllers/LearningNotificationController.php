<?php

namespace App\Http\Controllers;

use App\Models\CodingMatch;
use App\Models\LearningNotification;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\BlogLearningService;
use App\Services\LearningNotifications;
use App\Services\LearningNotificationTargets;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LearningNotificationController extends Controller
{
    private function actor(Request $request): Student|TeacherAccount
    {
        $actor = $request->routeIs('teacher.*') ? TeacherAccount::find(session('teacher_id')) : Student::with('classroom')->find(session('student_id'));
        abort_unless($actor, 401);

        return $actor;
    }

    private function owned(Student|TeacherAccount $actor)
    {
        $teacher = $actor instanceof TeacherAccount;

        return LearningNotification::where($teacher ? 'teacher_account_id' : 'student_id', $actor->id)->whereNull($teacher ? 'student_id' : 'teacher_account_id');
    }

    public function index(Request $request, LearningNotifications $service, LearningNotificationTargets $targets)
    {
        $actor = $this->actor($request);
        $filter = $request->validate(['category' => ['nullable', Rule::in(array_keys(config('classroom_notifications.categories')))], 'unread_only' => ['nullable', 'boolean'], 'before' => ['nullable', 'integer', 'min:1']]);
        $teacher = $actor instanceof TeacherAccount;
        app(BlogLearningService::class)->finalizeExpired(studentId: $teacher ? null : $actor->id, teacherId: $teacher ? $actor->id : null);
        $service->initialize($actor);
        $base = $this->owned($actor);
        $counts = (clone $base)->whereNull('read_at')->selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category');
        $latest = (clone $base)->max('id') ?? 0;
        $rows = (clone $base)->when($filter['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'))
            ->when($filter['before'] ?? null, fn ($q, $id) => $q->where('id', '<', $id))->latest('id')->limit(21)->get();
        $more = $rows->count() > 20;
        $items = $rows->take(20)->map(fn ($notice) => ['id' => $notice->id, 'title' => $notice->title, 'message' => $notice->message, 'category' => $notice->category,
            'unread' => $notice->read_at === null, 'time' => $notice->created_at->toIso8601String(), 'url' => $targets->url($notice, $actor)]);
        $invitations = $actor instanceof Student ? CodingMatch::where('status', 'inviting')->whereHas('participants', fn ($q) => $q->where('student_id', $actor->id)->where('response', 'invited'))->count() : 0;

        return response()->json(['unread' => $counts->sum(), 'counts' => $counts, 'items' => $items->values(), 'through' => (int) $latest,
            'next_cursor' => $more ? $items->last()['id'] : null, 'invitations' => $invitations])->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request)
    {
        $actor = $this->actor($request);
        $data = $request->validate(['through' => ['required_without:ids', 'integer', 'min:1'], 'ids' => ['required_without:through', 'array', 'min:1', 'max:50'], 'ids.*' => ['required', 'integer', 'distinct', 'min:1']]);
        $query = $this->owned($actor)->whereNull('read_at');
        if (isset($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        } else {
            $query->where('id', '<=', $data['through']);
        }
        $query->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifications marked as read.'])->header('Cache-Control', 'private, no-store');
    }
}
