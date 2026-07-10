<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    Brain,
    CheckCircle2,
    Circle,
    ListTodo,
    MessageSquare,
    Plus,
    RefreshCw,
    Sun,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { regenerate } from '@/routes/yoyu/briefing';
import { store as storeFocus, update as updateFocus } from '@/routes/yoyu/focus';
import { chat, home } from '@/routes/yoyu';
import {
    destroy as destroyTask,
    store as storeTask,
    update as updateTask,
} from '@/routes/yoyu/tasks';

type Task = {
    id: string;
    title: string;
    status: string;
    estimate_minutes: number;
};

type FocusItem = {
    id: string;
    status: string;
    text: string;
    memory_id: string;
};

type CalEvent = {
    id: string;
    title: string;
    start: string;
    end: string;
    place: string;
    travel_min: number;
    color: string;
};

interface Props {
    tasks: Task[];
    focusItems: FocusItem[];
    briefing: string | null;
    calendar: CalEvent[];
    clearDawnHand: { goal: string; action: string; estimate: number };
    recallPreview: string[];
    tab: string;
    chatReply: string | null;
    chatRecallCount: number | null;
}

const props = defineProps<Props>();

const currentTab = ref(props.tab || 'today');
const taskTitle = ref('');
const mindText = ref('');
const chatInput = ref('');
const chatHistory = ref<Array<{ role: string; content: string }>>([]);

watch(
    () => props.tab,
    (tab) => {
        currentTab.value = tab || 'today';
    },
);

watch(
    () => props.chatReply,
    (reply) => {
        if (reply) {
            chatHistory.value.push({ role: 'assistant', content: reply });
        }
    },
    { immediate: true },
);

const tabs = [
    { key: 'today', label: '今日', icon: Sun },
    { key: 'tasks', label: 'タスク', icon: ListTodo },
    { key: 'mind', label: '頭の中', icon: Brain },
    { key: 'chat', label: '秘書', icon: MessageSquare },
] as const;

function switchTab(key: string): void {
    currentTab.value = key;
    router.get(home.url({ query: { tab: key } }), {}, { preserveState: true, replace: true });
}

function fmtTime(iso: string): string {
    const d = new Date(iso);
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

const plannedTasks = computed(() =>
    props.tasks.filter((t) => t.status === 'planned' || t.status === 'doing'),
);

function sendChat(): void {
    const message = chatInput.value.trim();
    if (!message) {
        return;
    }
    chatHistory.value.push({ role: 'user', content: message });
    chatInput.value = '';
    router.post(chat.url(), {
        message,
        history: chatHistory.value.slice(0, -1),
    });
}

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '今日を回す場所',
    },
});
</script>

<template>
    <div class="space-y-5">
        <Head title="ヨユウ" />

        <nav
            class="flex flex-wrap gap-1 rounded-full border border-border bg-card p-1 shadow-sm"
        >
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-xs font-semibold transition-colors"
                :class="
                    currentTab === tab.key
                        ? 'bg-os-yoyu text-white'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="switchTab(tab.key)"
            >
                <component :is="tab.icon" :size="14" />
                {{ tab.label }}
            </button>
        </nav>

        <div v-if="currentTab === 'today'" class="grid gap-4 lg:grid-cols-2">
            <section class="space-y-4">
                <div
                    class="rounded-xl border border-os-yoyu/30 bg-os-yoyu/5 p-4"
                >
                    <div class="text-sm font-semibold text-os-yoyu">余裕メーター</div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        予定・移動・タスクから算出（MVPは簡易表示）。詰めすぎに注意。
                    </p>
                    <p class="mt-3 text-lg font-semibold">
                        未完了タスク {{ plannedTasks.length }} 件
                    </p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="mb-2 text-sm font-semibold text-cd-mist">
                        Clear Dawnからの、夢に向かう一手
                    </div>
                    <div class="font-medium">{{ clearDawnHand.action }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        目標: {{ clearDawnHand.goal }}（約{{ clearDawnHand.estimate }}分）
                    </div>
                    <Form
                        v-bind="storeTask.form()"
                        class="mt-3"
                        #default="{ processing }"
                    >
                        <input type="hidden" name="title" :value="clearDawnHand.action" />
                        <input
                            type="hidden"
                            name="estimate_minutes"
                            :value="clearDawnHand.estimate"
                        />
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            class="gap-1"
                            :disabled="processing"
                        >
                            <Plus :size="14" />
                            今日のタスクに入れる
                        </Button>
                    </Form>
                </div>
            </section>

            <section class="space-y-4">
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <div class="text-sm font-semibold text-os-yoyu">
                            朝ブリーフィング
                        </div>
                        <Form v-bind="regenerate.form()">
                            <Button type="submit" size="sm" variant="outline" class="gap-1">
                                <RefreshCw :size="13" />
                                更新
                            </Button>
                        </Form>
                    </div>
                    <pre
                        class="text-sm leading-relaxed whitespace-pre-wrap text-foreground"
                        >{{ briefing || 'まだありません。「更新」で生成できます。' }}</pre
                    >
                    <div
                        v-if="recallPreview.length"
                        class="mt-3 rounded-lg bg-os-kioku/5 p-2 text-xs text-os-kioku"
                    >
                        キオク Recall {{ recallPreview.length }}件を参照可能
                    </div>
                </div>

                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="mb-2 text-sm font-semibold text-muted-foreground">
                        今日の流れ（モック予定）
                    </div>
                    <div
                        v-for="event in calendar"
                        :key="event.id"
                        class="border-b border-border py-3 last:border-0"
                    >
                        <div class="text-sm font-medium">
                            <span :style="{ color: event.color }">{{
                                fmtTime(event.start)
                            }}</span>
                            {{ event.title }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            {{ event.place }}
                            <span v-if="event.travel_min">
                                · 移動{{ event.travel_min }}分
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div v-else-if="currentTab === 'tasks'" class="grid gap-4 lg:grid-cols-2">
            <Form
                v-bind="storeTask.form()"
                class="rounded-xl border border-border bg-card p-4"
                #default="{ processing }"
                @success="taskTitle = ''"
            >
                <div class="mb-3 text-sm font-semibold text-os-yoyu">タスクを追加</div>
                <div class="flex gap-2">
                    <Input
                        v-model="taskTitle"
                        name="title"
                        placeholder="今日〜近い未来の実行タスク"
                        class="flex-1"
                    />
                    <Button type="submit" size="icon" :disabled="processing || !taskTitle.trim()">
                        <Plus :size="16" />
                    </Button>
                </div>
            </Form>

            <div class="space-y-3">
                <div
                    v-for="task in tasks"
                    :key="task.id"
                    class="flex items-center gap-2 rounded-xl border border-border bg-card px-3 py-2"
                >
                    <Form v-bind="updateTask.form(task.id)">
                        <input
                            type="hidden"
                            name="status"
                            :value="task.status === 'done' ? 'planned' : 'done'"
                        />
                        <button type="submit" class="text-muted-foreground">
                            <CheckCircle2
                                v-if="task.status === 'done'"
                                :size="18"
                                class="text-os-yoyu"
                            />
                            <Circle v-else :size="18" />
                        </button>
                    </Form>
                    <div class="min-w-0 flex-1">
                        <div
                            class="truncate text-sm"
                            :class="task.status === 'done' ? 'line-through opacity-50' : ''"
                        >
                            {{ task.title }}
                        </div>
                        <div class="text-[11px] text-muted-foreground">
                            {{ task.status }} · {{ task.estimate_minutes }}分
                        </div>
                    </div>
                    <Form v-bind="destroyTask.form(task.id)">
                        <button type="submit" class="text-muted-foreground hover:text-destructive">
                            <Trash2 :size="14" />
                        </button>
                    </Form>
                </div>
            </div>
        </div>

        <div v-else-if="currentTab === 'mind'" class="grid gap-4 lg:grid-cols-2">
            <Form
                v-bind="storeFocus.form()"
                class="rounded-xl border border-border bg-card p-4"
                #default="{ processing }"
                @success="mindText = ''"
            >
                <div class="mb-3 text-sm font-semibold text-os-yoyu">
                    いま頭を占めていることを、下ろす
                </div>
                <textarea
                    v-model="mindText"
                    name="text"
                    rows="4"
                    class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu"
                    placeholder="悩み・考え・URL。原本はキオクに保存されます。"
                />
                <Button
                    type="submit"
                    class="mt-3 w-full bg-os-yoyu text-white hover:bg-os-yoyu/90"
                    :disabled="processing || !mindText.trim()"
                >
                    下ろす
                </Button>
                <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                    「整理済みにする」はインボックスから手放すだけで、キオクの記憶は残ります。
                </p>
            </Form>

            <div class="space-y-3">
                <div
                    v-for="item in focusItems"
                    :key="item.id"
                    class="rounded-xl border border-border bg-card p-3"
                >
                    <p class="text-sm leading-relaxed break-all">{{ item.text }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Form v-bind="updateFocus.form(item.id)">
                            <input type="hidden" name="convert_to_task" value="1" />
                            <input type="hidden" name="status" value="tasked" />
                            <Button type="submit" size="sm" variant="outline">タスク化</Button>
                        </Form>
                        <Form v-bind="updateFocus.form(item.id)">
                            <input type="hidden" name="status" value="snoozed" />
                            <Button type="submit" size="sm" variant="outline">後回し</Button>
                        </Form>
                        <Form v-bind="updateFocus.form(item.id)">
                            <input type="hidden" name="status" value="done" />
                            <Button type="submit" size="sm" variant="outline"
                                >整理済みにする</Button
                            >
                        </Form>
                    </div>
                </div>
                <div
                    v-if="focusItems.length === 0"
                    class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
                >
                    頭の中は空っぽです。いい状態。
                </div>
            </div>
        </div>

        <div v-else class="mx-auto flex max-w-2xl flex-col gap-3">
            <div
                class="min-h-72 space-y-3 rounded-xl border border-border bg-card p-4"
            >
                <div
                    v-if="chatHistory.length === 0"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    秘書のヨユウです。現在はライブデータ、過去はキオク Recall から見ます。
                    <div
                        v-if="recallPreview.length"
                        class="mt-2 text-xs text-os-kioku"
                    >
                        いま参照可能な記憶: {{ recallPreview.length }}件
                    </div>
                </div>
                <div
                    v-for="(msg, idx) in chatHistory"
                    :key="idx"
                    class="flex"
                    :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[85%] rounded-2xl px-3 py-2 text-sm leading-relaxed whitespace-pre-wrap"
                        :class="
                            msg.role === 'user'
                                ? 'bg-os-yoyu text-white'
                                : 'border border-border bg-background'
                        "
                    >
                        {{ msg.content }}
                    </div>
                </div>
            </div>
            <div class="flex gap-2 rounded-xl border border-border bg-card p-2">
                <Input
                    v-model="chatInput"
                    placeholder="秘書に相談する"
                    class="border-0 shadow-none focus-visible:ring-0"
                    @keydown.enter.prevent="sendChat"
                />
                <Button
                    type="button"
                    class="bg-os-yoyu text-white hover:bg-os-yoyu/90"
                    :disabled="!chatInput.trim()"
                    @click="sendChat"
                >
                    送信
                </Button>
            </div>
            <p class="text-center text-[11px] text-muted-foreground">
                AIキー未設定時はフォールバック応答になります。利用量は ai_usage_logs に記録されます。
            </p>
        </div>
    </div>
</template>
