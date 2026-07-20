<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Copy,
    Pencil,
    Plus,
    ScanLine,
    Search,
    Trash2,
    UtensilsCrossed,
} from '@lucide/vue';
import type { EChartsCoreOption } from 'echarts/core';
import { computed, ref, watch } from 'vue';
import BarcodeLookupModal from '@/components/BarcodeLookupModal.vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import DateNavigator from '@/components/DateNavigator.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import PageViewTabs from '@/components/PageViewTabs.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { apiFetch } from '@/lib/apiFetch';
import { PFC_COLORS } from '@/lib/pfcColors';
import type {
    FoodItem,
    MealEntry,
    MealSection,
    NutritionChartPoint,
    NutritionGoal,
    NutritionTotals,
} from '@/types/routine';

interface Props {
    date: string;
    from: string;
    to: string;
    sections: MealSection[];
    totals: NutritionTotals;
    goal: NutritionGoal | null;
    chartPoints: NutritionChartPoint[];
}

const props = defineProps<Props>();

type EntryTab = 'food' | 'direct';

const viewTabs = [
    { id: 'today', label: '今日' },
    { id: 'trends', label: '推移' },
    { id: 'settings', label: '設定' },
];

const activeTab = ref('today');
const showGoalModal = ref(false);
const showEntryModal = ref(false);
const editingEntry = ref<MealEntry | null>(null);
const entryMealType = ref<MealSection['meal_type']>('breakfast');
const entryTab = ref<EntryTab>('food');
const saving = ref(false);
const message = ref<string | null>(null);

const goalForm = ref({
    kcal: props.goal?.kcal ?? '2200',
    protein_g: props.goal?.protein_g ?? '120',
    fat_g: props.goal?.fat_g ?? '70',
    carb_g: props.goal?.carb_g ?? '250',
});

const foodQuery = ref('');
const foodResults = ref<FoodItem[]>([]);
const selectedFood = ref<FoodItem | null>(null);
const entryForm = ref({
    name: '',
    quantity: '1',
    kcal: '',
    protein_g: '',
    fat_g: '',
    carb_g: '',
    note: '',
    register_as_food: false,
});

const showBarcodeModal = ref(false);

const filterFrom = ref(props.from);
const filterTo = ref(props.to);

watch(
    () => props.goal,
    (goal) => {
        goalForm.value = {
            kcal: goal?.kcal ?? '2200',
            protein_g: goal?.protein_g ?? '120',
            fat_g: goal?.fat_g ?? '70',
            carb_g: goal?.carb_g ?? '250',
        };
    },
);

const remaining = computed(() => {
    if (!props.goal) {
        return null;
    }

    return {
        kcal: Math.max(0, Number(props.goal.kcal) - props.totals.kcal),
        protein_g: Math.max(
            0,
            Number(props.goal.protein_g) - props.totals.protein_g,
        ),
        fat_g: Math.max(0, Number(props.goal.fat_g) - props.totals.fat_g),
        carb_g: Math.max(0, Number(props.goal.carb_g) - props.totals.carb_g),
    };
});

const nextFoodHint = computed(() => {
    const left = remaining.value;

    if (left === null) {
        return '目標を設定すると、残りカロリーと次に摂るとよいものがわかります。';
    }

    if (left.protein_g >= left.carb_g * 0.4) {
        return 'たんぱく質を中心に、野菜や果物も取り入れるのがおすすめです。';
    }

    if (left.carb_g > left.protein_g) {
        return '炭水化物の余裕があります。次の食事でエネルギーを補いましょう。';
    }

    return '残りはバランスよく。無理なく目標に近づけましょう。';
});

const recordedEntries = computed(() =>
    props.sections.flatMap((section) =>
        section.entries.map((entry) => ({
            ...entry,
            sectionLabel: section.label,
        })),
    ),
);

const kcalAchievement = computed(() => {
    if (!props.goal) {
        return null;
    }

    const target = Number(props.goal.kcal);

    if (target <= 0) {
        return null;
    }

    return Math.round((props.totals.kcal / target) * 100);
});

const kcalChartOption = computed<EChartsCoreOption>(() => ({
    grid: { left: 48, right: 24, top: 24, bottom: 32 },
    tooltip: { trigger: 'axis' },
    xAxis: {
        type: 'category',
        data: props.chartPoints.map((point) => point.date),
        axisLabel: { color: '#5c5a6e', fontSize: 11 },
        axisLine: { lineStyle: { color: '#cfc8d8' } },
    },
    yAxis: {
        type: 'value',
        axisLabel: { color: '#5c5a6e', fontSize: 11 },
        splitLine: {
            lineStyle: { color: '#cfc8d8', opacity: 0.45 },
        },
    },
    series: [
        {
            name: 'kcal',
            type: 'line',
            smooth: true,
            symbol: 'circle',
            symbolSize: 8,
            data: props.chartPoints.map((point) => point.kcal),
            lineStyle: { color: '#5b5577', width: 2 },
            itemStyle: { color: '#5b5577' },
            areaStyle: { color: 'rgba(91, 85, 119, 0.12)' },
        },
    ],
}));

const pfcChartOption = computed<EChartsCoreOption>(() => ({
    grid: { left: 48, right: 24, top: 40, bottom: 32 },
    tooltip: { trigger: 'axis' },
    legend: {
        top: 0,
        textStyle: { color: '#5c5a6e', fontSize: 11 },
    },
    xAxis: {
        type: 'category',
        data: props.chartPoints.map((point) => point.date),
        axisLabel: { color: '#5c5a6e', fontSize: 11 },
        axisLine: { lineStyle: { color: '#cfc8d8' } },
    },
    yAxis: {
        type: 'value',
        axisLabel: { color: '#5c5a6e', fontSize: 11 },
        splitLine: {
            lineStyle: { color: '#cfc8d8', opacity: 0.45 },
        },
    },
    series: [
        {
            name: 'P',
            type: 'bar',
            stack: 'pfc',
            barMaxWidth: 36,
            data: props.chartPoints.map((point) => point.protein_g),
            itemStyle: { color: PFC_COLORS.p.hex, borderRadius: [0, 0, 0, 0] },
        },
        {
            name: 'F',
            type: 'bar',
            stack: 'pfc',
            barMaxWidth: 36,
            data: props.chartPoints.map((point) => point.fat_g),
            itemStyle: { color: PFC_COLORS.f.hex },
        },
        {
            name: 'C',
            type: 'bar',
            stack: 'pfc',
            barMaxWidth: 36,
            data: props.chartPoints.map((point) => point.carb_g),
            itemStyle: {
                color: PFC_COLORS.c.hex,
                borderRadius: [4, 4, 0, 0],
            },
        },
    ],
}));

const hasChartData = computed(() =>
    props.chartPoints.some(
        (point) =>
            point.kcal > 0 ||
            point.protein_g > 0 ||
            point.fat_g > 0 ||
            point.carb_g > 0,
    ),
);

function formatNum(value: string | number): string {
    return Number(value).toLocaleString('ja-JP', {
        maximumFractionDigits: 1,
    });
}

function openGoalModal(): void {
    showGoalModal.value = true;
}

async function saveGoal(): Promise<void> {
    saving.value = true;
    message.value = null;

    try {
        await apiFetch('/meals/goals', {
            method: 'PUT',
            body: JSON.stringify({
                kcal: Number(goalForm.value.kcal),
                protein_g: Number(goalForm.value.protein_g),
                fat_g: Number(goalForm.value.fat_g),
                carb_g: Number(goalForm.value.carb_g),
            }),
        });
        showGoalModal.value = false;
        message.value = '目標を保存しました。';
        router.reload({
            only: ['goal', 'totals', 'sections', 'chartPoints'],
        });
    } catch {
        message.value = '目標の保存に失敗しました。';
    } finally {
        saving.value = false;
    }
}

function resetEntryForm(): void {
    selectedFood.value = null;
    foodQuery.value = '';
    foodResults.value = [];
    entryTab.value = 'food';
    entryForm.value = {
        name: '',
        quantity: '1',
        kcal: '',
        protein_g: '',
        fat_g: '',
        carb_g: '',
        note: '',
        register_as_food: false,
    };
}

function nextMealType(): MealSection['meal_type'] {
    const empty = props.sections.find((section) => section.entries.length === 0);

    return empty?.meal_type ?? 'breakfast';
}

function openAddEntry(mealType?: MealSection['meal_type']): void {
    editingEntry.value = null;
    entryMealType.value = mealType ?? nextMealType();
    resetEntryForm();
    showEntryModal.value = true;
    void searchFoods('');
}

function openQuickFoodSearch(): void {
    openAddEntry();
    entryTab.value = 'food';
}

function openUsualMeals(): void {
    openAddEntry();
    entryTab.value = 'food';
}

function openBarcodeScanner(): void {
    showBarcodeModal.value = true;
}

function onBarcodeRegistered(food: FoodItem): void {
    message.value = `「${food.name}」をマイ食品に登録しました。`;
    router.reload({ only: ['sections', 'totals', 'chartPoints', 'goal'] });
}

function onBarcodeHit(food: FoodItem): void {
    selectFood(food);
    entryMealType.value = nextMealType();
    showEntryModal.value = true;
}

async function copyPreviousDay(): Promise<void> {
    saving.value = true;
    message.value = null;

    try {
        const data = await apiFetch<{ copied: number; reason?: string }>(
            '/meals/copy-previous-day',
            {
                method: 'POST',
                body: JSON.stringify({ date: props.date }),
            },
        );

        if (data.copied === 0) {
            message.value =
                data.reason === 'target_not_empty'
                    ? 'この日には既に食事記録があるためコピーしませんでした。'
                    : '前日の食事記録がありません。';
        } else {
            message.value = `前日の食事を ${data.copied} 件コピーしました。`;
            router.reload({
                only: ['sections', 'totals', 'chartPoints', 'goal'],
            });
        }
    } catch {
        message.value = '前日コピーに失敗しました。';
    } finally {
        saving.value = false;
    }
}

function openEditEntry(entry: MealEntry): void {
    editingEntry.value = entry;
    entryMealType.value = entry.meal_type;
    entryTab.value = entry.food_item_id ? 'food' : 'direct';
    selectedFood.value = null;
    entryForm.value = {
        name: entry.name,
        quantity: entry.quantity,
        kcal: entry.kcal,
        protein_g: entry.protein_g,
        fat_g: entry.fat_g,
        carb_g: entry.carb_g,
        note: entry.note ?? '',
        register_as_food: false,
    };
    showEntryModal.value = true;
}

let searchTimer: ReturnType<typeof setTimeout> | null = null;

function onFoodQueryInput(value: string | number): void {
    foodQuery.value = String(value);

    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        void searchFoods(foodQuery.value);
    }, 250);
}

async function searchFoods(query: string): Promise<void> {
    try {
        const params = new URLSearchParams();
        params.set('query', query);
        const data = await apiFetch<{ foods: FoodItem[] }>(
            `/meals/foods?${params.toString()}`,
        );
        foodResults.value = data.foods;
    } catch {
        foodResults.value = [];
    }
}

function selectFood(food: FoodItem): void {
    selectedFood.value = food;
    entryForm.value.name = food.name;
    entryForm.value.kcal = food.kcal;
    entryForm.value.protein_g = food.protein_g;
    entryForm.value.fat_g = food.fat_g;
    entryForm.value.carb_g = food.carb_g;
}

async function saveEntry(): Promise<void> {
    saving.value = true;
    message.value = null;

    const quantity = Number(entryForm.value.quantity);
    const payload: Record<string, unknown> = {
        eaten_on: props.date,
        meal_type: entryMealType.value,
        quantity,
        note: String(entryForm.value.note ?? '').trim() || null,
    };

    if (entryTab.value === 'food' && selectedFood.value) {
        payload.food_item_id = selectedFood.value.id;
        payload.name = selectedFood.value.name;
    } else if (editingEntry.value?.food_item_id && entryTab.value === 'food') {
        payload.food_item_id = editingEntry.value.food_item_id;
        payload.name = entryForm.value.name;
    } else {
        payload.name = String(entryForm.value.name ?? '').trim();
        payload.kcal = Number(entryForm.value.kcal);
        payload.protein_g = Number(entryForm.value.protein_g);
        payload.fat_g = Number(entryForm.value.fat_g);
        payload.carb_g = Number(entryForm.value.carb_g);
        payload.register_as_food = entryForm.value.register_as_food;
    }

    try {
        if (editingEntry.value) {
            await apiFetch(`/meals/${editingEntry.value.id}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
        } else {
            await apiFetch('/meals', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        }

        showEntryModal.value = false;
        message.value = '保存しました。';
        router.reload({
            only: ['sections', 'totals', 'chartPoints', 'goal'],
        });
    } catch {
        message.value = '保存に失敗しました。';
    } finally {
        saving.value = false;
    }
}

async function deleteEntry(entry: MealEntry): Promise<void> {
    if (!confirm(`${entry.name} を削除しますか？`)) {
        return;
    }

    await apiFetch(`/meals/${entry.id}`, { method: 'DELETE' });
    router.reload({ only: ['sections', 'totals', 'chartPoints'] });
}

function applyChartFilter(): void {
    router.get(
        '/meals',
        {
            date: props.date,
            from: filterFrom.value,
            to: filterTo.value,
        },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <Head title="食事記録" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-4 md:gap-5">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)]">
                <PageSectionCard>
                    <div class="flex flex-col gap-3">
                        <Link
                            :href="`/records?date=${date}`"
                            class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink-muted transition-colors hover:text-primary"
                        >
                            ← パフォーマンス管理
                        </Link>
                        <PageTitleOrnament
                            title="食事記録"
                            subtitle="残り摂取と次の一手を先に、記録は下で"
                            align="left"
                        />
                        <PageViewTabs
                            v-model="activeTab"
                            :tabs="viewTabs"
                            aria-label="食事記録表示切替"
                            class="mt-1"
                        />
                    </div>
                </PageSectionCard>

                <PageSectionCard
                    padding="sm"
                    class="flex items-center justify-center"
                >
                    <DateNavigator
                        :date="date"
                        route-url="/meals"
                        :reload-only="[
                            'sections',
                            'totals',
                            'goal',
                            'date',
                            'chartPoints',
                        ]"
                    />
                </PageSectionCard>
            </div>

            <p
                v-if="message"
                class="font-sans text-sm"
                :class="
                    message.includes('失敗') || message.includes('準備中')
                        ? message.includes('失敗')
                            ? 'text-destructive'
                            : 'text-cd-ink-muted'
                        : 'text-cd-moss'
                "
            >
                {{ message }}
            </p>

            <!-- 今日 -->
            <div
                v-show="activeTab === 'today'"
                id="panel-today"
                role="tabpanel"
                class="flex flex-col gap-4"
            >
                <PageSectionCard aria-label="残りの摂取目安">
                    <div class="grid gap-5 lg:grid-cols-[1.2fr_1fr]">
                        <div>
                            <p class="font-sans text-xs font-medium text-cd-ink-muted">
                                残りの摂取目安
                            </p>
                            <template v-if="remaining">
                                <p class="mt-2 font-sans text-3xl font-bold tracking-tight text-cd-ink">
                                    あと {{ formatNum(remaining.kcal) }}
                                    <span class="text-lg font-semibold text-cd-ink-muted">kcal</span>
                                </p>
                                <p class="mt-2 font-sans text-sm text-cd-ink-muted">
                                    目標達成に向けて、あとこれだけ摂れます。
                                </p>
                                <p
                                    v-if="kcalAchievement !== null"
                                    class="mt-1 font-sans text-xs text-cd-ink-muted"
                                >
                                    現在 {{ formatNum(totals.kcal) }} /
                                    {{ formatNum(goal!.kcal) }} kcal（{{ kcalAchievement }}%）
                                </p>
                            </template>
                            <template v-else>
                                <p class="mt-2 font-sans text-xl font-semibold text-cd-ink">
                                    目標未設定
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    class="mt-3 font-sans"
                                    @click="
                                        activeTab = 'settings';
                                        openGoalModal();
                                    "
                                >
                                    目標を設定
                                </Button>
                            </template>
                        </div>

                        <div>
                            <p class="font-sans text-xs font-medium text-cd-ink-muted">
                                残りの PFC
                            </p>
                            <div
                                v-if="remaining"
                                class="mt-3 grid grid-cols-3 gap-2"
                            >
                                <div class="rounded-xl bg-cd-pfc-p/15 px-3 py-3 text-center">
                                    <p class="font-sans text-[11px] font-medium text-cd-pfc-p">P</p>
                                    <p class="mt-1 font-sans text-lg font-bold text-cd-ink">
                                        +{{ formatNum(remaining.protein_g) }}
                                        <span class="text-xs font-medium">g</span>
                                    </p>
                                </div>
                                <div class="rounded-xl bg-cd-pfc-f/15 px-3 py-3 text-center">
                                    <p class="font-sans text-[11px] font-medium text-cd-pfc-f">F</p>
                                    <p class="mt-1 font-sans text-lg font-bold text-cd-ink">
                                        +{{ formatNum(remaining.fat_g) }}
                                        <span class="text-xs font-medium">g</span>
                                    </p>
                                </div>
                                <div class="rounded-xl bg-cd-pfc-c/15 px-3 py-3 text-center">
                                    <p class="font-sans text-[11px] font-medium text-cd-pfc-c">C</p>
                                    <p class="mt-1 font-sans text-lg font-bold text-cd-ink">
                                        +{{ formatNum(remaining.carb_g) }}
                                        <span class="text-xs font-medium">g</span>
                                    </p>
                                </div>
                            </div>
                            <p class="mt-3 font-sans text-sm leading-relaxed text-cd-ink">
                                <span class="text-xs font-medium text-cd-ink-muted">次に摂るとよいもの</span><br>
                                {{ nextFoodHint }}
                            </p>
                        </div>
                    </div>
                </PageSectionCard>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <button
                        type="button"
                        class="rounded-2xl border border-cd-line bg-cd-surface px-4 py-4 text-left shadow-sm transition-colors hover:border-primary/40 hover:bg-primary/5"
                        @click="openUsualMeals"
                    >
                        <UtensilsCrossed :size="18" :stroke-width="1.6" class="text-primary" />
                        <p class="mt-3 font-sans text-sm font-semibold text-cd-ink">いつもの食事</p>
                        <p class="mt-1 font-sans text-xs text-cd-ink-muted">よく食べるメニューから選ぶ</p>
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border border-cd-line bg-cd-surface px-4 py-4 text-left shadow-sm transition-colors hover:border-primary/40 hover:bg-primary/5"
                        @click="openBarcodeScanner"
                    >
                        <ScanLine :size="18" :stroke-width="1.6" class="text-primary" />
                        <p class="mt-3 font-sans text-sm font-semibold text-cd-ink">バーコード</p>
                        <p class="mt-1 font-sans text-xs text-cd-ink-muted">スキャンして食品を登録</p>
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border border-cd-line bg-cd-surface px-4 py-4 text-left shadow-sm transition-colors hover:border-primary/40 hover:bg-primary/5"
                        @click="openQuickFoodSearch"
                    >
                        <Search :size="18" :stroke-width="1.6" class="text-primary" />
                        <p class="mt-3 font-sans text-sm font-semibold text-cd-ink">食品を検索</p>
                        <p class="mt-1 font-sans text-xs text-cd-ink-muted">食品名やマイ食品から探す</p>
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border border-cd-line bg-cd-surface px-4 py-4 text-left shadow-sm transition-colors hover:border-primary/40 hover:bg-primary/5"
                        :disabled="saving"
                        @click="copyPreviousDay"
                    >
                        <Copy :size="18" :stroke-width="1.6" class="text-primary" />
                        <p class="mt-3 font-sans text-sm font-semibold text-cd-ink">昨日からコピー</p>
                        <p class="mt-1 font-sans text-xs text-cd-ink-muted">前日の食事をまとめて追加</p>
                    </button>
                </div>

                <PageSectionCard padding="none" aria-label="今日の食事記録">
                    <div class="flex items-center justify-between gap-3 border-b border-cd-line px-5 py-4">
                        <div>
                            <h2 class="font-sans text-base font-semibold text-cd-ink">
                                今日の食事記録
                            </h2>
                            <p class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                                記録済みだけを一覧表示します
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            class="font-sans"
                            @click="openAddEntry()"
                        >
                            <Plus :size="14" :stroke-width="1.6" />
                            食事を追加
                        </Button>
                    </div>

                    <ul v-if="recordedEntries.length > 0" class="divide-y divide-cd-line">
                        <li
                            v-for="entry in recordedEntries"
                            :key="entry.id"
                            class="flex items-start justify-between gap-3 px-5 py-4"
                        >
                            <div class="min-w-0">
                                <p class="font-sans text-xs font-medium text-primary">
                                    {{ entry.sectionLabel }}
                                </p>
                                <p class="mt-1 font-sans text-sm font-semibold text-cd-ink">
                                    {{ entry.name }}
                                    <span class="font-normal text-cd-ink-muted">
                                        × {{ formatNum(entry.quantity) }}
                                    </span>
                                </p>
                                <p class="mt-1 font-sans text-xs">
                                    <span class="text-cd-pfc-p">P {{ formatNum(entry.protein_g) }}g</span>
                                    ·
                                    <span class="text-cd-pfc-f">F {{ formatNum(entry.fat_g) }}g</span>
                                    ·
                                    <span class="text-cd-pfc-c">C {{ formatNum(entry.carb_g) }}g</span>
                                    ·
                                    <span class="text-cd-ink-muted">{{ formatNum(entry.kcal) }} kcal</span>
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    :aria-label="`${entry.name} を編集`"
                                    @click="openEditEntry(entry)"
                                >
                                    <Pencil :size="14" :stroke-width="1.6" />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    :aria-label="`${entry.name} を削除`"
                                    @click="deleteEntry(entry)"
                                >
                                    <Trash2 :size="14" :stroke-width="1.6" />
                                </Button>
                            </div>
                        </li>
                    </ul>

                    <div
                        v-else
                        class="mx-5 my-5 rounded-xl border border-dashed border-cd-line px-4 py-10 text-center"
                    >
                        <p class="font-sans text-sm text-cd-ink-muted">
                            まだ食事は記録されていません。上のクイック操作か「食事を追加」から始めましょう。
                        </p>
                    </div>
                </PageSectionCard>
            </div>

            <!-- 推移 -->
            <div
                v-show="activeTab === 'trends'"
                id="panel-trends"
                role="tabpanel"
                class="flex flex-col gap-4"
            >
                <PageSectionCard aria-label="推移">
                    <div class="flex flex-col gap-4">
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                        >
                            <PageTitleOrnament
                                title="推移"
                                subtitle="期間内の日別合計を表示します。"
                                align="left"
                            />

                            <div class="flex flex-wrap items-end gap-3">
                                <div class="flex flex-col gap-1">
                                    <Label class="font-sans text-xs">開始</Label>
                                    <Input v-model="filterFrom" type="date" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="font-sans text-xs">終了</Label>
                                    <Input v-model="filterTo" type="date" />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="font-sans"
                                    @click="applyChartFilter"
                                >
                                    反映
                                </Button>
                            </div>
                        </div>

                        <div
                            v-if="!hasChartData"
                            class="rounded-xl border border-dashed border-cd-line px-4 py-10 text-center"
                        >
                            <p class="font-sans text-sm text-cd-ink-muted">
                                この期間の食事記録がまだありません。記録すると推移グラフが表示されます。
                            </p>
                        </div>

                        <div v-else class="grid gap-6 lg:grid-cols-2">
                            <div>
                                <h3 class="mb-2 font-sans text-sm font-semibold text-cd-ink">
                                    エネルギー（kcal）
                                </h3>
                                <BaseChart :option="kcalChartOption" />
                            </div>
                            <div>
                                <h3 class="mb-2 font-sans text-sm font-semibold text-cd-ink">
                                    PFC（g）
                                </h3>
                                <BaseChart :option="pfcChartOption" />
                            </div>
                        </div>
                    </div>
                </PageSectionCard>
            </div>

            <!-- 設定 -->
            <div
                v-show="activeTab === 'settings'"
                id="panel-settings"
                role="tabpanel"
                class="flex flex-col gap-4"
            >
                <PageSectionCard>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-sans text-base font-semibold text-cd-ink">
                                栄養目標
                            </h2>
                            <p class="mt-1 font-sans text-sm text-cd-ink-muted">
                                1 日あたりの目標値。残り kcal / PFC の基準になります。
                            </p>
                            <p
                                v-if="goal"
                                class="mt-3 font-sans text-sm text-cd-ink"
                            >
                                現在: {{ formatNum(goal.kcal) }} kcal /
                                P {{ formatNum(goal.protein_g) }}g /
                                F {{ formatNum(goal.fat_g) }}g /
                                C {{ formatNum(goal.carb_g) }}g
                            </p>
                            <p v-else class="mt-3 font-sans text-sm text-cd-ink-muted">
                                まだ目標がありません。
                            </p>
                        </div>
                        <Button
                            type="button"
                            class="font-sans"
                            @click="openGoalModal"
                        >
                            目標を設定
                        </Button>
                    </div>
                    <Link
                        href="/meals/foods"
                        class="mt-5 inline-flex items-center gap-2 font-sans text-sm font-medium text-primary underline-offset-2 hover:underline"
                    >
                        <UtensilsCrossed :size="14" :stroke-width="1.6" />
                        マイ食品を管理
                    </Link>
                </PageSectionCard>
            </div>
        </div>
    </div>

    <Dialog :open="showGoalModal" @update:open="(v) => (showGoalModal = v)">
        <DialogContent class="bg-cd-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="font-sans">栄養目標</DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    1 日あたりの目標値を設定します。
                </DialogDescription>
            </DialogHeader>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">kcal</Label>
                    <Input v-model="goalForm.kcal" type="number" min="0" step="1" />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">P (g)</Label>
                    <Input
                        v-model="goalForm.protein_g"
                        type="number"
                        min="0"
                        step="0.1"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">F (g)</Label>
                    <Input
                        v-model="goalForm.fat_g"
                        type="number"
                        min="0"
                        step="0.1"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">C (g)</Label>
                    <Input
                        v-model="goalForm.carb_g"
                        type="number"
                        min="0"
                        step="0.1"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    class="font-sans"
                    @click="showGoalModal = false"
                >
                    キャンセル
                </Button>
                <Button
                    type="button"
                    class="font-sans"
                    :disabled="saving"
                    @click="saveGoal"
                >
                    保存
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <BarcodeLookupModal
        v-model:open="showBarcodeModal"
        @food-registered="onBarcodeRegistered"
        @food-hit="onBarcodeHit"
    />

    <Dialog :open="showEntryModal" @update:open="(v) => (showEntryModal = v)">
        <DialogContent class="bg-cd-surface sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="font-sans">
                    {{ editingEntry ? '食事を編集' : '食事を追加' }}
                </DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    マイ食品から選ぶか、直接入力できます。数量はサービング倍率です。
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-1">
                <Label class="font-sans text-xs">区分</Label>
                <select
                    v-model="entryMealType"
                    class="rounded-md border border-input bg-transparent px-3 py-2 font-sans text-sm"
                >
                    <option
                        v-for="section in sections"
                        :key="section.meal_type"
                        :value="section.meal_type"
                    >
                        {{ section.label }}
                    </option>
                </select>
            </div>

            <div class="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    :variant="entryTab === 'food' ? 'default' : 'outline'"
                    class="font-sans"
                    @click="entryTab = 'food'"
                >
                    マイ食品から
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="entryTab === 'direct' ? 'default' : 'outline'"
                    class="font-sans"
                    @click="entryTab = 'direct'"
                >
                    直接入力
                </Button>
            </div>

            <div v-if="entryTab === 'food'" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">検索</Label>
                    <Input
                        :model-value="foodQuery"
                        type="text"
                        placeholder="食品名"
                        @update:model-value="onFoodQueryInput"
                    />
                </div>
                <ul class="max-h-40 overflow-y-auto rounded-lg border border-cd-line">
                    <li
                        v-for="food in foodResults"
                        :key="food.id"
                        class="cursor-pointer border-b border-cd-line px-3 py-2 last:border-b-0 hover:bg-muted/40"
                        :class="selectedFood?.id === food.id ? 'bg-primary/5' : ''"
                        @click="selectFood(food)"
                    >
                        <p class="font-sans text-sm font-medium text-cd-ink">
                            {{ food.name }}
                        </p>
                        <p class="font-sans text-xs text-cd-ink-muted">
                            {{ food.serving_label }} ·
                            {{ formatNum(food.kcal) }} kcal
                        </p>
                    </li>
                    <li
                        v-if="foodResults.length === 0"
                        class="px-3 py-4 font-sans text-sm text-cd-ink-muted"
                    >
                        該当するマイ食品がありません。
                    </li>
                </ul>
            </div>

            <div v-else class="grid grid-cols-2 gap-3">
                <div class="col-span-2 flex flex-col gap-1">
                    <Label class="font-sans text-xs">名前</Label>
                    <Input v-model="entryForm.name" type="text" maxlength="100" />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">kcal</Label>
                    <Input v-model="entryForm.kcal" type="number" min="0" step="0.1" />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">P (g)</Label>
                    <Input
                        v-model="entryForm.protein_g"
                        type="number"
                        min="0"
                        step="0.1"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">F (g)</Label>
                    <Input
                        v-model="entryForm.fat_g"
                        type="number"
                        min="0"
                        step="0.1"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">C (g)</Label>
                    <Input
                        v-model="entryForm.carb_g"
                        type="number"
                        min="0"
                        step="0.1"
                    />
                </div>
                <label
                    v-if="!editingEntry"
                    class="col-span-2 flex items-center gap-2 font-sans text-sm text-cd-ink"
                >
                    <input
                        v-model="entryForm.register_as_food"
                        type="checkbox"
                        class="rounded border-cd-line"
                    />
                    マイ食品にも登録する
                </label>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">数量（サービング倍率）</Label>
                    <Input
                        v-model="entryForm.quantity"
                        type="number"
                        min="0.1"
                        max="100"
                        step="0.1"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">メモ</Label>
                    <Input v-model="entryForm.note" type="text" maxlength="500" />
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    class="font-sans"
                    @click="showEntryModal = false"
                >
                    キャンセル
                </Button>
                <Button
                    type="button"
                    class="font-sans"
                    :disabled="
                        saving ||
                        (entryTab === 'food' &&
                            !selectedFood &&
                            !editingEntry?.food_item_id)
                    "
                    @click="saveEntry"
                >
                    保存
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
