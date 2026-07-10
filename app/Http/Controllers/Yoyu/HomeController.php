<?php

namespace App\Http\Controllers\Yoyu;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\RecallService;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\PromptTemplate;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Models\YoyuFocusItem;
use App\Domain\Yoyu\Models\YoyuTask;
use App\Domain\Yoyu\Support\MockCalendar;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HomeController extends Controller
{
    public function index(Request $request, RecallService $recall): Response
    {
        $user = $request->user();

        $tasks = YoyuTask::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (YoyuTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'estimate_minutes' => $task->estimate_minutes,
            ]);

        $focusItems = YoyuFocusItem::query()
            ->with('memory')
            ->where('user_id', $user->id)
            ->whereIn('status', ['open', 'snoozed'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (YoyuFocusItem $item) => [
                'id' => $item->id,
                'status' => $item->status,
                'text' => $item->memory?->raw_content ?? '',
                'memory_id' => $item->memory_id,
            ]);

        $briefing = YoyuBriefing::query()
            ->where('user_id', $user->id)
            ->whereDate('date', today())
            ->first();

        $recallLines = $recall->for((int) $user->id, '今日の予定 余裕 タスク', 5, countReference: false);

        return Inertia::render('Yoyu/Index', [
            'tasks' => $tasks,
            'focusItems' => $focusItems,
            'briefing' => $briefing?->body,
            'calendar' => MockCalendar::todayEvents(),
            'clearDawnHand' => MockCalendar::clearDawnHand(),
            'recallPreview' => $recallLines,
            'tab' => $request->string('tab')->toString() ?: 'today',
            'chatReply' => $request->session()->pull('chat_reply'),
            'chatRecallCount' => $request->session()->pull('chat_recall_count'),
        ]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'estimate_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'status' => ['nullable', 'string', 'in:inbox,planned,doing,done,snoozed,cancelled'],
        ]);

        YoyuTask::query()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'estimate_minutes' => $data['estimate_minutes'] ?? 30,
            'status' => $data['status'] ?? 'planned',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'タスクを追加しました。']);

        return redirect()->route('yoyu.home', ['tab' => 'tasks']);
    }

    public function updateTask(Request $request, YoyuTask $task): RedirectResponse
    {
        abort_unless((int) $task->user_id === (int) $request->user()->id, 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:inbox,planned,doing,done,snoozed,cancelled'],
        ]);

        $task->update(['status' => $data['status']]);

        return redirect()->route('yoyu.home', ['tab' => 'tasks']);
    }

    public function destroyTask(Request $request, YoyuTask $task): RedirectResponse
    {
        abort_unless((int) $task->user_id === (int) $request->user()->id, 404);
        $task->delete();

        return redirect()->route('yoyu.home', ['tab' => 'tasks']);
    }

    public function storeFocus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:10000'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $data): void {
            $memory = Memory::query()->create([
                'user_id' => $user->id,
                'source_type' => 'yoyu',
                'memory_type' => null,
                'title' => '整理中…',
                'raw_content' => $data['text'],
                'captured_at' => now(),
                'status' => 'captured',
            ]);

            YoyuFocusItem::query()->create([
                'user_id' => $user->id,
                'memory_id' => $memory->id,
                'status' => 'open',
            ]);

            EnrichMemoryJob::dispatch($memory->id)->afterResponse();
            $memory->update(['status' => 'enriching']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => '頭の中に下ろしました（キオクにも保存）。']);

        return redirect()->route('yoyu.home', ['tab' => 'mind']);
    }

    public function updateFocus(Request $request, YoyuFocusItem $focus): RedirectResponse
    {
        abort_unless((int) $focus->user_id === (int) $request->user()->id, 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:open,snoozed,done,tasked'],
            'convert_to_task' => ['nullable', 'boolean'],
        ]);

        if (($data['convert_to_task'] ?? false) === true) {
            $task = YoyuTask::query()->create([
                'user_id' => $request->user()->id,
                'title' => mb_substr($focus->memory?->raw_content ?? 'タスク', 0, 40),
                'status' => 'planned',
                'estimate_minutes' => 30,
            ]);
            $focus->update([
                'status' => 'tasked',
                'converted_task_id' => $task->id,
            ]);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'タスクに変換しました。']);

            return redirect()->route('yoyu.home', ['tab' => 'mind']);
        }

        $focus->update([
            'status' => $data['status'],
            'snoozed_until' => $data['status'] === 'snoozed' ? now()->addDay() : null,
        ]);

        return redirect()->route('yoyu.home', ['tab' => 'mind']);
    }

    public function regenerateBriefing(Request $request, AiGateway $ai, RecallService $recall): RedirectResponse
    {
        $user = $request->user();
        $recallLines = $recall->for((int) $user->id, '朝ブリーフィング 今日の予定', 5);
        $calendar = MockCalendar::todayEvents();
        $hand = MockCalendar::clearDawnHand();

        $context = "予定:\n".collect($calendar)->map(function (array $e): string {
            $start = Carbon::parse($e['start'])->format('H:i');

            return "- {$e['title']} {$start}";
        })->implode("\n")."\n"
            ."Clear Dawnの一手: {$hand['action']}\n"
            ."過去の経験:\n".implode("\n", $recallLines);

        try {
            $result = $ai->complete(
                userId: (int) $user->id,
                feature: 'yoyu.briefing',
                prompt: PromptTemplate::make(
                    'yoyu.briefing.v1',
                    'あなたは優しい秘書ヨユウです。急かさない口調で朝ブリーフィングを作ります。',
                    "形式:\n■ 今日の全体像\n■ 最も注意する時刻\n■ 夢に向かう一手\n■ 過去のパターンに基づく注意\n■ 手放していいこと\n220文字以内。\n\n{$context}",
                ),
                tier: 'cheap',
                maxTokens: 600,
            );
            $body = trim($result['text']);
        } catch (Throwable) {
            $body = "■ 今日は予定があります\n■ 出発時刻に注意しましょう\n■ {$hand['action']}\n■ 過去の詰まりパターンに注意\n■ 不要な予定は手放して大丈夫です";
        }

        YoyuBriefing::query()->updateOrCreate(
            ['user_id' => $user->id, 'date' => today()->toDateString()],
            ['body' => $body],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '朝ブリーフィングを更新しました。']);

        return redirect()->route('yoyu.home', ['tab' => 'today']);
    }

    public function chat(Request $request, AiGateway $ai, RecallService $recall): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'history' => ['nullable', 'array', 'max:30'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        $recallLines = $recall->for((int) $user->id, $data['message'], 5);
        $tasks = YoyuTask::query()->where('user_id', $user->id)->whereNotIn('status', ['done', 'cancelled'])->get();
        $hand = MockCalendar::clearDawnHand();

        $live = "タスク:\n".$tasks->map(fn ($t) => "- [{$t->status}] {$t->title}")->implode("\n")
            ."\nClear Dawnの一手: {$hand['action']}\n過去:\n".implode("\n", $recallLines);

        $history = array_slice($data['history'] ?? [], -30);
        $messages = [
            ...collect($history)->map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']])->all(),
            ['role' => 'user', 'content' => $data['message']],
        ];

        try {
            $result = $ai->complete(
                userId: (int) $user->id,
                feature: 'yoyu.chat',
                prompt: PromptTemplate::make(
                    'yoyu.chat.v1',
                    "あなたはユーザー専属のAI秘書「ヨユウ」です。短く・優先順位を明確に・安心できる口調で答えます。タスク追加を提案する場合のみ末尾に [[TASK: 内容]] を付けます。\n\n{$live}",
                    '',
                ),
                tier: 'strong',
                maxTokens: 1100,
                messages: [
                    ['role' => 'user', 'content' => 'コンテキストを理解したら「準備OK」とだけ返してください。'],
                    ['role' => 'assistant', 'content' => '準備OK'],
                    ...$messages,
                ],
            );
            $reply = trim($result['text']);
        } catch (Throwable) {
            $reply = '接続エラーです。少し待ってからもう一度送ってください。';
        }

        return redirect()
            ->route('yoyu.home', ['tab' => 'chat'])
            ->with('chat_reply', $reply)
            ->with('chat_recall_count', count($recallLines));
    }
}
