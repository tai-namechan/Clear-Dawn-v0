<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    Bot,
    Brain,
    Calendar,
    Car,
    CheckCircle2,
    Circle,
    Clock,
    Compass,
    Database,
    ListTodo,
    MapPin,
    Moon,
    Plus,
    RefreshCw,
    Send,
    Settings,
    Sun,
    Trash2,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import YoyuTub from '@/components/yoyu/YoyuTub.vue';
import {
    isYoyuBriefingPending,
    useYoyuBriefingPoll,
    yoyuBriefingLabel
    
} from '@/composables/useYoyuBriefingPoll';
import type {YoyuBriefingStatus} from '@/composables/useYoyuBriefingPoll';
import { isYoyuChatQuotaExceeded } from '@/lib/aiUsageMessages';
import {
    BUFFER_MIN,
    departInfo,
    fmtTime,
    PREP_MIN,
    TUB_LABEL,
    yoyuCalc
    
} from '@/lib/yoyuCalc';
import type {CalEvent} from '@/lib/yoyuCalc';
import { chat, settings } from '@/routes/yoyu';
import { regenerate } from '@/routes/yoyu/briefing';
import {
    store as storeFocus,
    update as updateFocus,
} from '@/routes/yoyu/focus';
import { upsert as upsertPlace } from '@/routes/yoyu/places';
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

type CalendarConnection = {
    status: string;
    synced_at: string | null;
    is_stale: boolean;
    warning_code: string | null;
    account_email: string | null;
    all_day_titles: string[];
};

type ClearDawnHandProp = {
    id?: string;
    goal: string;
    action: string;
    estimate: number;
    life_area?: string;
};

type AnalysisProp = {
    briefing_date: string;
    timezone: string;
    margin: {
        margin_score: number;
        margin_label: string;
        busy_minutes: number;
        task_minutes: number;
        working_minutes: number;
    };
    gaps: {
        busy_minutes: number;
        gaps: Array<{
            key: string;
            start: string;
            end: string;
            minutes: number;
        }>;
    };
};

interface Props {
    tasks: Task[];
    focusItems: FocusItem[];
    briefing: string | null;
    briefingStatus: YoyuBriefingStatus;
    calendar: CalEvent[];
    calendarConnection: CalendarConnection;
    clearDawnHand: ClearDawnHandProp | null;
    analysis: AnalysisProp | null;
    travelLead: { prep_minutes: number; buffer_minutes: number };
    recallPreview: string[];
    tab: string;
    chatReply: string | null;
    chatErrorCode: string | null;
    chatRecallCount: number | null;
}

const props = defineProps<Props>();

const prepMin = computed(() => props.travelLead?.prep_minutes ?? PREP_MIN);
const bufferMin = computed(() => props.travelLead?.buffer_minutes ?? BUFFER_MIN);

const ESTIMATE_OPTIONS = [15, 30, 45, 60, 90, 120, 180, 240] as const;

const currentTab = ref(props.tab || 'today');
const taskTitle = ref('');
const taskEstimate = ref(30);
const mindText = ref('');
const chatInput = ref('');
const chatHistory = ref<Array<{ role: string; content: string }>>([]);
const nowMs = ref(Date.now());
const doneEventIds = ref<string[]>([]);
const briefingStartedAt = ref<number | null>(null);
let timer: ReturnType<typeof setInterval> | undefined;

useYoyuBriefingPoll(() => props.briefingStatus);

const briefingPending = computed(() =>
    isYoyuBriefingPending(props.briefingStatus),
);

const briefingStatusLabel = computed(() =>
    yoyuBriefingLabel(
        props.briefingStatus,
        briefingStartedAt.value,
        nowMs.value,
    ),
);

watch(
    () => props.briefingStatus,
    (status, previous) => {
        if (
            isYoyuBriefingPending(status) &&
            !isYoyuBriefingPending(previous ?? null)
        ) {
            briefingStartedAt.value = Date.now();
        }

        if (!isYoyuBriefingPending(status)) {
            briefingStartedAt.value = null;
        }
    },
    { immediate: true },
);

const chatSuggestions = [
    '今日を焦らず乗り切る段取りを立てて',
    'ヨガの前の空き時間、何に使うべき？',
    '湯舟があふれてる。何を手放せばいい？',
    '前にも同じ悩みがなかったか教えて',
];

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

const isQuotaExceededChat = computed(() =>
    isYoyuChatQuotaExceeded(props.chatErrorCode),
);

onMounted(() => {
    timer = setInterval(() => {
        nowMs.value = Date.now();
    }, 30000);
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});

const statusLabel: Record<string, { label: string; color: string }> = {
    inbox: { label: '受信箱', color: '#6B7683' },
    planned: { label: '今日やる', color: '#129488' },
    doing: { label: '実行中', color: '#4A7DC4' },
    done: { label: '完了', color: '#43A860' },
    snoozed: { label: '後回し', color: '#DF9A2E' },
};

const taskGroups = computed(() => [
    {
        label: '今日やる',
        items: props.tasks.filter(
            (t) => t.status === 'planned' || t.status === 'doing',
        ),
    },
    {
        label: '受信箱',
        items: props.tasks.filter((t) => t.status === 'inbox'),
    },
    {
        label: '後回し',
        items: props.tasks.filter((t) => t.status === 'snoozed'),
    },
    {
        label: '完了',
        items: props.tasks.filter((t) => t.status === 'done'),
    },
]);

const nextEvent = computed(() =>
    props.calendar.find(
        (e) =>
            new Date(e.end).getTime() > nowMs.value &&
            !doneEventIds.value.includes(e.id),
    ),
);

const hero = computed(() => {
    const event = nextEvent.value;

    if (!event) {
        return null;
    }

    const d = departInfo(event, nowMs.value, prepMin.value, bufferMin.value);
    const min = d.travel
        ? d.min
        : Math.round((new Date(event.start).getTime() - nowMs.value) / 60000);
    let mood = '#43A860';
    let moodBg = '#E8F5EC';
    let moodText = 'まだ余裕があります';

    if (min <= 10) {
        mood = '#D9534F';
        moodBg = '#FBE8E7';
        moodText =
            min >= 0 ? '急がず、でも今すぐ動きましょう' : '時刻を過ぎています';
    } else if (min <= 30) {
        mood = '#DF9A2E';
        moodBg = '#FBF1DE';
        moodText = 'そろそろ準備を';
    }

    return { event, d, min, mood, moodBg, moodText };
});

const calendarEmptyMessage = computed(() => {
    const status = props.calendarConnection.status;

    if (status === 'disconnected') {
        return 'Googleカレンダーを接続すると、今日の予定が表示されます。';
    }

    if (status === 'syncing' || status === 'idle') {
        return 'カレンダーを同期しています…';
    }

    if (status === 'error') {
        return props.calendarConnection.warning_code ===
            'reauthorization_required'
            ? 'Googleカレンダーの再接続が必要です。'
            : 'カレンダーの同期に失敗しました。設定から再試行できます。';
    }

    if (props.calendar.length === 0) {
        return '今日の予定はありません';
    }

    return '今日の予定はすべて終わりました';
});

const tubStatus = computed(
    () =>
        yoyuCalc(
            nowMs.value,
            props.calendar,
            doneEventIds.value,
            props.tasks,
            prepMin.value,
            bufferMin.value,
        ).status,
);

function toggleEvent(id: string): void {
    if (doneEventIds.value.includes(id)) {
        doneEventIds.value = doneEventIds.value.filter((x) => x !== id);
    } else {
        doneEventIds.value = [...doneEventIds.value, id];
    }
}

function sendChat(message?: string): void {
    const text = (message ?? chatInput.value).trim();

    if (!text) {
        return;
    }

    chatHistory.value.push({ role: 'user', content: text });
    chatInput.value = '';
    router.post(chat.url(), {
        message: text,
        history: chatHistory.value.slice(0, -1),
    });
}

function isLive(event: CalEvent): boolean {
    const start = new Date(event.start).getTime();
    const end = new Date(event.end).getTime();

    return start <= nowMs.value && nowMs.value <= end;
}

function isDone(event: CalEvent): boolean {
    return (
        doneEventIds.value.includes(event.id) ||
        new Date(event.end).getTime() < nowMs.value
    );
}

function estimateOptionsFor(current: number): number[] {
    if (
        ESTIMATE_OPTIONS.includes(current as (typeof ESTIMATE_OPTIONS)[number])
    ) {
        return [...ESTIMATE_OPTIONS];
    }

    return [...ESTIMATE_OPTIONS, current].sort((a, b) => a - b);
}

defineOptions({
    layout: {
        title: '',
        subtitle: '',
    },
});
</script>

<template>
    <div class="space-y-4">
        <Head title="ヨユウ" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs text-os-sub">
                {{
                    new Date().toLocaleDateString('ja-JP', {
                        month: 'long',
                        day: 'numeric',
                        weekday: 'long',
                    })
                }}
                — 焦らず、前へ回すAI秘書
            </div>
            <Link
                :href="settings()"
                class="inline-flex items-center gap-1 text-xs text-os-sub hover:text-os-ink"
            >
                <Settings :size="13" />
                設定
            </Link>
        </div>

        <div
            v-if="
                currentTab === 'today' &&
                (calendarConnection.status === 'disconnected' ||
                    calendarConnection.status === 'syncing' ||
                    calendarConnection.status === 'idle' ||
                    calendarConnection.status === 'error')
            "
            class="flex flex-wrap items-center justify-between gap-2 rounded-[14px] border border-os-line bg-white px-4 py-3 text-[12.5px] text-os-sub"
        >
            <span class="inline-flex items-center gap-1.5">
                <Calendar :size="14" class="text-[#4A7DC4]" />
                {{
                    calendarConnection.status === 'error'
                        ? calendarConnection.warning_code ===
                          'reauthorization_required'
                            ? 'Googleカレンダーの再接続が必要です。'
                            : 'カレンダーの同期に失敗しました。しばらくして再試行してください。'
                        : calendarConnection.status === 'syncing' ||
                            calendarConnection.status === 'idle'
                          ? 'カレンダーを同期しています…'
                          : 'Googleカレンダーを接続すると、今日の予定が表示されます。'
                }}
            </span>
            <Link
                v-if="
                    calendarConnection.status === 'disconnected' ||
                    calendarConnection.warning_code ===
                        'reauthorization_required'
                "
                :href="settings()"
                class="font-bold text-[#4A7DC4] hover:underline"
            >
                {{
                    calendarConnection.status === 'error'
                        ? '再接続する'
                        : '接続する'
                }}
            </Link>
            <Link
                v-else-if="calendarConnection.status === 'error'"
                :href="settings()"
                class="font-bold text-[#4A7DC4] hover:underline"
            >
                設定を開く
            </Link>
        </div>

        <div
            v-if="
                currentTab === 'today' &&
                calendarConnection.all_day_titles.length
            "
            class="flex flex-wrap items-center gap-2 text-[12px] text-os-sub"
        >
            <span class="font-bold">終日:</span>
            <span
                v-for="title in calendarConnection.all_day_titles"
                :key="title"
                class="rounded-full bg-os-line/40 px-2.5 py-0.5"
            >
                {{ title }}
            </span>
        </div>

        <!-- Today -->
        <div v-if="currentTab === 'today'" class="grid gap-4 lg:grid-cols-2">
            <section class="space-y-3.5">
                <YoyuTub
                    :now-ms="nowMs"
                    :calendar="calendar"
                    :done-event-ids="doneEventIds"
                    :tasks="tasks"
                />

                <div
                    v-if="hero"
                    class="rounded-[18px] border p-[18px] shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
                    :style="{
                        background: hero.moodBg,
                        borderColor: hero.mood + '44',
                    }"
                >
                    <div
                        class="mb-2.5 flex items-center gap-2 text-[12.5px] font-bold"
                        :style="{ color: hero.mood }"
                    >
                        <Car :size="15" />
                        {{ hero.moodText }}
                    </div>
                    <div class="flex flex-wrap items-baseline gap-2.5">
                        <span
                            class="font-serif text-[38px] leading-none font-bold text-os-ink"
                        >
                            {{
                                hero.min >= 0
                                    ? `あと${hero.min}分`
                                    : `${-hero.min}分超過`
                            }}
                        </span>
                        <span class="text-sm font-semibold text-os-sub">
                            で{{ hero.d.travel ? '出発' : '開始' }}（{{
                                fmtTime(
                                    new Date(
                                        hero.d.travel
                                            ? hero.d.depart
                                            : new Date(
                                                  hero.event.start,
                                              ).getTime(),
                                    ).toISOString(),
                                )
                            }}）
                        </span>
                    </div>
                    <div class="mt-3 text-[13.5px] leading-relaxed text-os-ink">
                        <span class="font-bold"
                            >{{ fmtTime(hero.event.start) }}
                            {{ hero.event.title }}</span
                        >
                        <div
                            class="mt-1.5 flex flex-wrap gap-3 text-[12.5px] text-os-sub"
                        >
                            <span class="inline-flex items-center gap-1">
                                <MapPin :size="13" />{{ hero.event.place }}
                            </span>
                            <span
                                v-if="hero.d.travel"
                                class="inline-flex items-center gap-1"
                            >
                                <Car :size="13" />移動{{
                                    hero.event.travel_min
                                }}分
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <Clock :size="13" />支度{{ prepMin }}分＋余白{{
                                    bufferMin
                                }}分
                            </span>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="rounded-[18px] border border-[#43A86044] bg-[#E8F5EC] p-[18px] text-center"
                >
                    <div class="text-[15px] font-bold text-[#43A860]">
                        {{ calendarEmptyMessage }}
                    </div>
                </div>

                <div
                    v-if="clearDawnHand"
                    class="rounded-[18px] border border-[#4A7DC444] bg-white p-[18px] shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
                >
                    <div
                        class="mb-3 flex items-center gap-1.5 text-xs font-bold tracking-wide text-[#4A7DC4]"
                    >
                        <Compass :size="14" />
                        Clear Dawnからの、夢に向かう一手
                    </div>
                    <div class="text-sm font-bold">
                        {{ clearDawnHand.action }}
                    </div>
                    <div class="mt-1 mb-3 text-xs text-os-sub">
                        領域: {{ clearDawnHand.goal }}（所要 約{{
                            clearDawnHand.estimate
                        }}分）
                    </div>
                    <Form v-bind="storeTask.form()" #default="{ processing }">
                        <input
                            type="hidden"
                            name="title"
                            :value="clearDawnHand.action"
                        />
                        <input
                            type="hidden"
                            name="estimate_minutes"
                            :value="clearDawnHand.estimate"
                        />
                        <Button
                            type="submit"
                            size="sm"
                            class="gap-1 rounded-full border border-os-yoyu/30 bg-os-yoyu-soft font-bold text-os-yoyu hover:bg-os-yoyu-soft"
                            variant="outline"
                            :disabled="processing"
                        >
                            <Plus :size="13" />
                            今日のタスクに入れる
                        </Button>
                    </Form>
                </div>
                <div
                    v-else
                    class="rounded-[18px] border border-dashed border-[#4A7DC444] bg-white p-[18px] text-center text-sm text-os-sub shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
                >
                    Clear Dawnの「今やるべきこと」に未完了の項目がありません。
                </div>
            </section>

            <section class="space-y-3.5">
                <div
                    class="rounded-[18px] border border-os-line bg-white p-[18px] shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
                >
                    <div class="mb-2.5 flex items-center justify-between gap-2">
                        <div
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wide text-os-yoyu"
                        >
                            <Sun :size="14" />
                            朝ブリーフィング（1日1回・保存済み）
                        </div>
                        <Form v-bind="regenerate.form()">
                            <Button
                                type="submit"
                                size="sm"
                                class="gap-1 rounded-full border border-os-yoyu/30 bg-os-yoyu-soft font-bold text-os-yoyu hover:bg-os-yoyu-soft"
                                variant="outline"
                                :disabled="briefingPending"
                            >
                                <RefreshCw
                                    :size="13"
                                    :class="
                                        briefingPending ? 'animate-spin' : ''
                                    "
                                />
                                更新
                            </Button>
                        </Form>
                    </div>
                    <div
                        v-if="briefingPending || briefingStatus === 'failed'"
                        class="mb-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11.5px] font-bold"
                        :class="
                            briefingStatus === 'failed'
                                ? 'bg-[#F8E9E4] text-[#C05A48]'
                                : 'bg-os-yoyu-soft text-os-yoyu'
                        "
                    >
                        {{ briefingStatusLabel }}
                    </div>
                    <pre
                        class="text-[13.5px] leading-[1.95] whitespace-pre-wrap text-os-ink"
                        >{{
                            briefing ||
                            'まだありません。「更新」で生成できます。'
                        }}</pre>
                    <div
                        v-if="tubStatus === 'over'"
                        class="mt-2.5 rounded-[10px] bg-[#FBE8E7] px-3 py-2 text-[12.5px] text-[#D9534F]"
                    >
                        通知: 余裕メーターが「{{
                            TUB_LABEL.over
                        }}」です。何か1つ手放す提案を秘書に相談できます。
                    </div>
                    <div
                        v-if="recallPreview.length"
                        class="mt-2.5 rounded-lg bg-os-kioku-soft px-2.5 py-2 text-xs text-os-kioku"
                    >
                        キオク Recall {{ recallPreview.length }}件を参照可能
                    </div>
                </div>

                <div
                    class="rounded-[18px] border border-os-line bg-white p-[18px] shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
                >
                    <div
                        class="mb-2.5 flex items-center gap-1.5 text-xs font-bold tracking-wide text-[#4A7DC4]"
                    >
                        今日の流れ（出発時刻つき・Googleカレンダー読み取り想定）
                    </div>
                    <div
                        v-for="event in calendar"
                        :key="event.id"
                        class="flex items-start gap-3 border-b border-os-line py-3 last:border-0"
                        :class="isDone(event) ? 'opacity-60' : ''"
                    >
                        <button
                            type="button"
                            class="mt-0.5"
                            @click="toggleEvent(event.id)"
                        >
                            <CheckCircle2
                                v-if="isDone(event)"
                                :size="18"
                                class="text-[#43A860]"
                            />
                            <Circle
                                v-else
                                :size="18"
                                :style="{
                                    color: isLive(event)
                                        ? event.color
                                        : '#A2ACB8',
                                }"
                            />
                        </button>
                        <div class="min-w-0 flex-1">
                            <div
                                class="text-[13.5px] font-semibold text-os-ink"
                            >
                                <span
                                    class="font-serif"
                                    :style="{ color: event.color }"
                                    >{{ fmtTime(event.start) }}</span
                                >
                                {{ event.title }}
                                <span
                                    v-if="isLive(event) && !isDone(event)"
                                    class="ml-2 rounded-full bg-[#E8F5EC] px-2 py-0.5 text-[10.5px] text-[#43A860]"
                                    >進行中</span
                                >
                            </div>
                            <div
                                v-if="!isDone(event) && event.travel_min !== null"
                                class="mt-1 flex flex-wrap items-center gap-1 text-xs text-os-sub"
                            >
                                <Car
                                    :size="12"
                                    :style="{ color: event.color }"
                                />
                                <span
                                    class="font-bold"
                                    :style="{ color: event.color }"
                                >
                                    {{
                                        fmtTime(
                                            new Date(
                                                departInfo(
                                                    event,
                                                    nowMs,
                                                    prepMin,
                                                    bufferMin,
                                                ).depart,
                                            ).toISOString(),
                                        )
                                    }}に出発
                                </span>
                                <span
                                    >（移動{{ event.travel_min }}分＋支度{{
                                        prepMin
                                    }}分＋余白{{ bufferMin }}分）</span
                                >
                            </div>
                            <div
                                v-else-if="!isDone(event) && event.place"
                                class="mt-1 text-xs text-os-sub"
                            >
                                <MapPin :size="12" class="mr-1 inline" />{{
                                    event.place
                                }}
                            </div>
                            <div
                                v-else-if="!isDone(event)"
                                class="mt-1 text-xs text-os-sub"
                            >
                                <MapPin :size="12" class="mr-1 inline" />場所なし
                            </div>
                            <Form
                                v-if="!isDone(event) && event.place"
                                v-bind="upsertPlace.form()"
                                class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11.5px] text-os-sub"
                                #default="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="name"
                                    :value="event.place"
                                />
                                <span>{{
                                    event.travel_min == null
                                        ? '移動時間未登録'
                                        : '移動時間'
                                }}</span>
                                <input
                                    type="number"
                                    name="travel_minutes"
                                    min="0"
                                    max="480"
                                    :value="event.travel_min ?? 20"
                                    class="w-16 rounded-lg border border-os-line bg-os-yoyu-bg px-2 py-1 text-[12px] outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                                    required
                                />
                                <span>分</span>
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="h-7 rounded-full border border-os-yoyu/30 bg-os-yoyu-soft px-2.5 text-[11px] font-bold text-os-yoyu hover:bg-os-yoyu-soft"
                                    variant="outline"
                                    :disabled="processing"
                                >
                                    {{
                                        event.travel_min == null
                                            ? '登録'
                                            : '更新'
                                    }}
                                </Button>
                            </Form>
                            <Form
                                v-else-if="!isDone(event) && !event.place"
                                v-bind="upsertPlace.form()"
                                class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11.5px] text-os-sub"
                                #default="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="external_id"
                                    :value="event.id"
                                />
                                <span class="text-[#DF9A2E]">移動時間未登録</span>
                                <input
                                    type="text"
                                    name="name"
                                    maxlength="255"
                                    required
                                    placeholder="場所名"
                                    class="w-28 rounded-lg border border-os-line bg-os-yoyu-bg px-2 py-1 text-[12px] outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                                />
                                <input
                                    type="number"
                                    name="travel_minutes"
                                    min="0"
                                    max="480"
                                    value="20"
                                    class="w-16 rounded-lg border border-os-line bg-os-yoyu-bg px-2 py-1 text-[12px] outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                                    required
                                />
                                <span>分</span>
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="h-7 rounded-full border border-os-yoyu/30 bg-os-yoyu-soft px-2.5 text-[11px] font-bold text-os-yoyu hover:bg-os-yoyu-soft"
                                    variant="outline"
                                    :disabled="processing"
                                >
                                    登録
                                </Button>
                            </Form>
                        </div>
                    </div>
                    <p class="mt-3 text-[11.5px] leading-relaxed text-os-sub">
                        移動時間は場所ごとに手動登録。MVPでは Maps API 不使用。
                    </p>
                </div>
            </section>
        </div>

        <!-- Tasks -->
        <div
            v-else-if="currentTab === 'tasks'"
            class="grid gap-4 lg:grid-cols-2"
        >
            <Form
                v-bind="storeTask.form()"
                class="rounded-[18px] border border-os-yoyu/25 bg-white p-[18px] shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
                #default="{ processing }"
                @success="taskTitle = ''"
            >
                <div
                    class="mb-3 flex items-center gap-1.5 text-xs font-bold tracking-wide text-os-yoyu"
                >
                    <ListTodo :size="14" />
                    タスクを追加
                </div>
                <div class="flex gap-2">
                    <input
                        v-model="taskTitle"
                        name="title"
                        placeholder="今日〜近い未来の実行タスク（Enterで追加）"
                        class="min-w-0 flex-1 rounded-xl border border-os-line bg-os-yoyu-bg px-3.5 py-2.5 text-[13.5px] outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                    <select
                        v-model.number="taskEstimate"
                        name="estimate_minutes"
                        class="w-[88px] shrink-0 rounded-xl border border-os-line bg-os-yoyu-bg px-2 text-[12.5px] outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                        aria-label="見積時間（分）"
                    >
                        <option
                            v-for="minutes in ESTIMATE_OPTIONS"
                            :key="minutes"
                            :value="minutes"
                        >
                            {{ minutes }}分
                        </option>
                    </select>
                    <Button
                        type="submit"
                        size="icon"
                        class="h-[42px] w-[42px] rounded-xl bg-os-yoyu text-white shadow-[0_3px_10px_rgba(18,148,136,0.3)] hover:bg-os-yoyu/90"
                        :disabled="processing || !taskTitle.trim()"
                        :class="taskTitle.trim() ? 'opacity-100' : 'opacity-40'"
                    >
                        <Plus :size="16" />
                    </Button>
                </div>
                <p class="mt-2.5 text-[11.5px] leading-relaxed text-os-sub">
                    目標・ロードマップはClear
                    Dawnで。ここに入るのは「実行」だけ。見積時間は余裕メーターに算入。
                </p>
            </Form>

            <div class="space-y-3.5">
                <div
                    v-for="group in taskGroups"
                    :key="group.label"
                    v-show="group.items.length"
                    class="rounded-[18px] border border-os-line bg-white p-[18px] shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
                >
                    <div class="mb-2 text-xs font-bold text-os-sub">
                        {{ group.label }} — {{ group.items.length }}件
                    </div>
                    <div
                        v-for="task in group.items"
                        :key="task.id"
                        class="flex items-center gap-2.5 border-b border-os-line py-2.5 last:border-0"
                        :class="task.status === 'done' ? 'opacity-50' : ''"
                    >
                        <Form v-bind="updateTask.form(task.id)">
                            <input
                                type="hidden"
                                name="status"
                                :value="
                                    task.status === 'done' ? 'planned' : 'done'
                                "
                            />
                            <button type="submit">
                                <CheckCircle2
                                    v-if="task.status === 'done'"
                                    :size="17"
                                    class="text-[#43A860]"
                                />
                                <Circle
                                    v-else
                                    :size="17"
                                    class="text-os-faint"
                                />
                            </button>
                        </Form>
                        <div class="min-w-0 flex-1">
                            <div
                                class="text-[13px]"
                                :class="
                                    task.status === 'done' ? 'line-through' : ''
                                "
                            >
                                {{ task.title }}
                            </div>
                            <div
                                class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[10.5px]"
                                :style="{
                                    color:
                                        statusLabel[task.status]?.color ||
                                        '#6B7683',
                                }"
                            >
                                <span>{{
                                    statusLabel[task.status]?.label ||
                                    task.status
                                }}</span>
                                <span>・</span>
                                <Form
                                    v-if="task.status !== 'done'"
                                    v-bind="updateTask.form(task.id)"
                                    class="inline-flex items-center"
                                >
                                    <select
                                        name="estimate_minutes"
                                        class="rounded-md border border-os-line bg-transparent px-1 py-0.5 text-[10.5px] outline-none"
                                        :value="task.estimate_minutes"
                                        aria-label="見積時間を変更"
                                        @change="
                                            (
                                                $event.target as HTMLSelectElement
                                            ).form?.requestSubmit()
                                        "
                                    >
                                        <option
                                            v-for="minutes in estimateOptionsFor(
                                                task.estimate_minutes,
                                            )"
                                            :key="minutes"
                                            :value="minutes"
                                        >
                                            {{ minutes }}分
                                        </option>
                                    </select>
                                </Form>
                                <span v-else
                                    >{{ task.estimate_minutes }}分</span
                                >
                            </div>
                        </div>
                        <Form
                            v-if="
                                task.status !== 'done' &&
                                task.status !== 'snoozed'
                            "
                            v-bind="updateTask.form(task.id)"
                        >
                            <input
                                type="hidden"
                                name="status"
                                value="snoozed"
                            />
                            <button
                                type="submit"
                                class="flex h-[30px] w-[30px] items-center justify-center rounded-[9px] border border-os-line bg-[#F2F3EF] text-os-sub"
                                title="後回し"
                            >
                                <Moon :size="13" />
                            </button>
                        </Form>
                        <Form v-bind="destroyTask.form(task.id)">
                            <button
                                type="submit"
                                class="flex h-[30px] w-[30px] items-center justify-center rounded-[9px] border border-os-line bg-[#F2F3EF] text-os-sub hover:text-destructive"
                            >
                                <Trash2 :size="13" />
                            </button>
                        </Form>
                    </div>
                </div>
                <div
                    v-if="tasks.length === 0"
                    class="rounded-[18px] border border-os-line bg-white p-8 text-center text-[12.5px] text-os-sub"
                >
                    タスクはありません。
                </div>
            </div>
        </div>

        <!-- Mind -->
        <div
            v-else-if="currentTab === 'mind'"
            class="grid gap-4 lg:grid-cols-2"
        >
            <div class="space-y-3.5">
                <Form
                    v-bind="storeFocus.form()"
                    class="rounded-[18px] border border-os-yoyu/25 bg-white p-[18px] shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
                    #default="{ processing }"
                    @success="mindText = ''"
                >
                    <div
                        class="mb-3 flex items-center gap-1.5 text-xs font-bold tracking-wide text-os-yoyu"
                    >
                        <Brain :size="14" />
                        いま頭を占めていることを、下ろす
                    </div>
                    <textarea
                        v-model="mindText"
                        name="text"
                        rows="4"
                        class="w-full resize-y rounded-xl border border-os-line bg-os-yoyu-bg px-3.5 py-3 text-[13.5px] leading-relaxed outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                        placeholder="悩み・考え・URL、なんでも。&#10;原本はキオクのmemoriesに保存され、ここには「最近・未整理」だけが表示されます。"
                    />
                    <Button
                        type="submit"
                        class="mt-2.5 h-11 w-full gap-2 rounded-[14px] bg-os-yoyu text-[13.5px] font-bold text-white shadow-[0_4px_14px_rgba(18,148,136,0.25)] hover:bg-os-yoyu/90"
                        :disabled="processing || !mindText.trim()"
                        :class="mindText.trim() ? 'opacity-100' : 'opacity-40'"
                    >
                        <Send :size="14" />
                        下ろす
                    </Button>
                </Form>
                <div
                    class="rounded-[18px] border border-os-line bg-white p-[18px] text-xs leading-relaxed text-os-sub"
                >
                    ここは
                    <b>思考インボックス</b>
                    です。過去も含めた蓄積・検索・再利用は
                    <span class="font-bold text-os-kioku"> キオク </span>
                    で行います。「整理済みにする」を押しても記憶は消えません —
                    インボックスから手放すだけで、キオクには残り続けます。
                </div>
            </div>

            <div class="space-y-3.5">
                <div
                    class="rounded-[18px] border border-os-line bg-white p-[18px] shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
                >
                    <div
                        class="mb-3 flex items-center gap-1.5 text-xs font-bold tracking-wide text-os-yoyu"
                    >
                        <Brain :size="14" />
                        未整理 — {{ focusItems.length }}件
                    </div>
                    <div
                        v-if="focusItems.length === 0"
                        class="text-[12.5px] text-os-sub"
                    >
                        頭の中は空っぽです。いい状態。
                    </div>
                    <div
                        v-for="item in focusItems"
                        :key="item.id"
                        class="border-b border-os-line py-2.5 last:border-0"
                    >
                        <p class="mb-2 text-[13px] leading-relaxed break-all">
                            {{ item.text }}
                            <span
                                v-if="item.status === 'snoozed'"
                                class="ml-2 rounded-full bg-[#FBF1DE] px-2 py-0.5 text-[10.5px] text-[#DF9A2E]"
                                >後回し</span
                            >
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <Form v-bind="updateFocus.form(item.id)">
                                <input
                                    type="hidden"
                                    name="convert_to_task"
                                    value="1"
                                />
                                <input
                                    type="hidden"
                                    name="status"
                                    value="tasked"
                                />
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-1 rounded-full border border-os-yoyu/40 bg-os-yoyu-soft px-2.5 py-1.5 text-[11.5px] font-bold text-os-yoyu"
                                >
                                    <ListTodo :size="12" />
                                    タスク化
                                </button>
                            </Form>
                            <Form v-bind="updateFocus.form(item.id)">
                                <input
                                    type="hidden"
                                    name="status"
                                    value="snoozed"
                                />
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-1 rounded-full border border-[#DF9A2E55] bg-[#FBF1DE] px-2.5 py-1.5 text-[11.5px] font-bold text-[#DF9A2E]"
                                >
                                    <Moon :size="12" />
                                    後回し
                                </button>
                            </Form>
                            <Form v-bind="updateFocus.form(item.id)">
                                <input
                                    type="hidden"
                                    name="status"
                                    value="done"
                                />
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-1 rounded-full border border-os-kioku/40 bg-[#F0EDFA] px-2.5 py-1.5 text-[11.5px] font-bold text-os-kioku"
                                >
                                    <Archive :size="12" />
                                    整理済みにする
                                </button>
                            </Form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat -->
        <div v-else class="mx-auto flex max-w-[760px] flex-col gap-3">
            <div
                class="flex min-h-[420px] flex-col gap-3 rounded-[18px] border border-os-line bg-white p-4 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
            >
                <div
                    v-if="chatHistory.length === 0"
                    class="flex flex-1 flex-col items-center justify-center px-4 py-8 text-center"
                >
                    <div
                        class="mb-3 flex h-[54px] w-[54px] items-center justify-center rounded-full bg-gradient-to-br from-os-yoyu to-[#0B7A70] shadow-[0_6px_20px_rgba(18,148,136,0.27)]"
                    >
                        <Bot :size="25" class="text-white" />
                    </div>
                    <div class="mb-1.5 text-[15px] font-bold">
                        秘書のヨユウです
                    </div>
                    <p class="mb-4 text-[12.5px] leading-relaxed text-os-sub">
                        現在の予定・タスクはライブで、過去の経験はキオクから見ています。<br />
                        タスクや予定は、あなたの確認なしに変更しません。
                    </p>
                    <div class="flex w-full max-w-md flex-col gap-2">
                        <button
                            v-for="s in chatSuggestions"
                            :key="s"
                            type="button"
                            class="rounded-xl border border-os-line bg-[#F7F8F5] px-3.5 py-2.5 text-left text-[12.5px] leading-snug text-os-ink hover:border-os-yoyu/40"
                            @click="sendChat(s)"
                        >
                            {{ s }}
                        </button>
                    </div>
                </div>

                <template v-else>
                    <div
                        v-for="(msg, idx) in chatHistory"
                        :key="idx"
                        class="flex flex-col gap-1.5"
                        :class="
                            msg.role === 'user' ? 'items-end' : 'items-start'
                        "
                    >
                        <div
                            class="max-w-[85%] rounded-2xl px-3.5 py-2.5 text-[13.5px] leading-relaxed whitespace-pre-wrap shadow-[0_1px_3px_rgba(38,48,58,0.06)]"
                            :class="
                                msg.role === 'user'
                                    ? 'rounded-br-sm bg-os-yoyu text-white'
                                    : 'rounded-bl-sm border border-os-line bg-white text-os-ink'
                            "
                            :data-test="
                                msg.role === 'assistant' &&
                                idx === chatHistory.length - 1 &&
                                isQuotaExceededChat
                                    ? 'yoyu-chat-quota-exceeded'
                                    : undefined
                            "
                        >
                            {{ msg.content }}
                        </div>
                        <div
                            v-if="
                                msg.role === 'assistant' &&
                                chatRecallCount &&
                                idx === chatHistory.length - 1
                            "
                            class="inline-flex items-center gap-1 rounded-full border border-os-kioku/30 bg-[#F0EDFA] px-2.5 py-1 text-[11px] text-os-kioku"
                        >
                            <Database :size="11" />
                            キオクの記憶{{ chatRecallCount }}件を参照
                        </div>
                    </div>
                </template>
            </div>

            <div
                class="flex gap-2 rounded-2xl border border-os-line bg-white p-1.5 pl-4 shadow-[0_2px_8px_rgba(38,48,58,0.06)]"
            >
                <input
                    v-model="chatInput"
                    placeholder="秘書に相談する"
                    class="min-w-0 flex-1 bg-transparent text-[13.5px] outline-none"
                    @keydown.enter.prevent="sendChat()"
                />
                <Button
                    type="button"
                    class="h-[42px] w-[42px] rounded-xl bg-os-yoyu text-white shadow-[0_3px_10px_rgba(18,148,136,0.3)] hover:bg-os-yoyu/90"
                    :disabled="!chatInput.trim()"
                    :class="chatInput.trim() ? 'opacity-100' : 'opacity-40'"
                    @click="sendChat()"
                >
                    <Send :size="15" />
                </Button>
            </div>
            <p class="text-center text-[11px] leading-relaxed text-os-sub">
                現在=ライブデータ／過去=キオクRecallの二層でAIに渡しています。AIキー未設定時はフォールバック応答になります。
            </p>
        </div>
    </div>
</template>
