<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, UtensilsCrossed } from '@lucide/vue';
import type { EChartsCoreOption } from 'echarts/core';
import { computed, ref, watch } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import DateNavigator from '@/components/DateNavigator.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
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

const pfcEnergy = computed(() => {
    const p = props.totals.protein_g * 4;
    const f = props.totals.fat_g * 9;
    const c = props.totals.carb_g * 4;
    const total = p + f + c;

    if (total <= 0) {
        return { p: 0, f: 0, c: 0, total: 0 };
    }

    return {
        p: Math.round((p / total) * 100),
        f: Math.round((f / total) * 100),
        c: Math.round((c / total) * 100),
        total,
    };
});

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
        axisLabel: { color: 'var(--cd-ink-muted)', fontSize: 11 },
        axisLine: { lineStyle: { color: 'var(--cd-line)' } },
    },
    yAxis: {
        type: 'value',
        axisLabel: { color: 'var(--cd-ink-muted)', fontSize: 11 },
        splitLine: {
            lineStyle: { color: 'var(--cd-line)', opacity: 0.4 },
        },
    },
    series: [
        {
            name: 'kcal',
            type: 'line',
            smooth: true,
            symbol: 'circle',
            symbolSize: 6,
            data: props.chartPoints.map((point) => point.kcal),
            lineStyle: { color: 'var(--chart-1)', width: 2 },
            itemStyle: { color: 'var(--chart-1)' },
            areaStyle: {
                color: 'color-mix(in oklab, var(--chart-1) 12%, transparent)',
            },
        },
    ],
}));

const pfcChartOption = computed<EChartsCoreOption>(() => ({
    grid: { left: 48, right: 24, top: 40, bottom: 32 },
    tooltip: { trigger: 'axis' },
    legend: {
        top: 0,
        textStyle: { color: 'var(--cd-ink-muted)', fontSize: 11 },
    },
    xAxis: {
        type: 'category',
        data: props.chartPoints.map((point) => point.date),
        axisLabel: { color: 'var(--cd-ink-muted)', fontSize: 11 },
        axisLine: { lineStyle: { color: 'var(--cd-line)' } },
    },
    yAxis: {
        type: 'value',
        axisLabel: { color: 'var(--cd-ink-muted)', fontSize: 11 },
        splitLine: {
            lineStyle: { color: 'var(--cd-line)', opacity: 0.4 },
        },
    },
    series: [
        {
            name: 'P',
            type: 'bar',
            stack: 'pfc',
            data: props.chartPoints.map((point) => point.protein_g),
            itemStyle: { color: 'var(--chart-2)' },
        },
        {
            name: 'F',
            type: 'bar',
            stack: 'pfc',
            data: props.chartPoints.map((point) => point.fat_g),
            itemStyle: { color: 'var(--chart-3)' },
        },
        {
            name: 'C',
            type: 'bar',
            stack: 'pfc',
            data: props.chartPoints.map((point) => point.carb_g),
            itemStyle: { color: 'var(--chart-4)' },
        },
    ],
}));

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

function openAddEntry(mealType: MealSection['meal_type']): void {
    editingEntry.value = null;
    entryMealType.value = mealType;
    resetEntryForm();
    showEntryModal.value = true;
    void searchFoods('');
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
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <PageTitleOrnament
                        title="食事記録"
                        subtitle="その日の食事と PFC を記録します。"
                        align="left"
                    />
                    <Link
                        href="/meals/foods"
                        class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink-muted transition-colors hover:text-primary"
                    >
                        <UtensilsCrossed :size="14" :stroke-width="1.6" />
                        マイ食品
                    </Link>
                </div>
            </PageSectionCard>

            <PageSectionCard padding="sm">
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

            <PageSectionCard aria-label="日次サマリ">
                <div class="flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-sans text-xs text-cd-ink-muted">
                                合計エネルギー
                            </p>
                            <p
                                class="mt-1 font-sans text-3xl font-semibold tracking-tight text-cd-ink"
                            >
                                {{ formatNum(totals.kcal) }}
                                <span class="text-base font-medium text-cd-ink-muted"
                                    >kcal</span
                                >
                            </p>
                            <p
                                v-if="kcalAchievement !== null"
                                class="mt-1 font-sans text-sm text-cd-moss"
                            >
                                目標達成率 {{ kcalAchievement }}%
                            </p>
                            <p
                                v-else
                                class="mt-1 font-sans text-sm text-cd-ink-muted"
                            >
                                目標未設定
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            class="font-sans"
                            @click="openGoalModal"
                        >
                            目標を設定
                        </Button>
                    </div>

                    <div>
                        <p class="mb-2 font-sans text-xs text-cd-ink-muted">
                            PFC バランス（エネルギー比）
                        </p>
                        <div
                            class="flex h-3 overflow-hidden rounded-full bg-cd-line/40"
                        >
                            <div
                                class="bg-[var(--chart-2)]"
                                :style="{ width: `${pfcEnergy.p}%` }"
                            />
                            <div
                                class="bg-[var(--chart-3)]"
                                :style="{ width: `${pfcEnergy.f}%` }"
                            />
                            <div
                                class="bg-[var(--chart-4)]"
                                :style="{ width: `${pfcEnergy.c}%` }"
                            />
                        </div>
                        <div
                            class="mt-2 flex flex-wrap gap-3 font-sans text-xs text-cd-ink-muted"
                        >
                            <span>P {{ formatNum(totals.protein_g) }}g ({{ pfcEnergy.p }}%)</span>
                            <span>F {{ formatNum(totals.fat_g) }}g ({{ pfcEnergy.f }}%)</span>
                            <span>C {{ formatNum(totals.carb_g) }}g ({{ pfcEnergy.c }}%)</span>
                        </div>
                    </div>

                    <p
                        v-if="message"
                        class="font-sans text-sm"
                        :class="
                            message.includes('失敗')
                                ? 'text-destructive'
                                : 'text-cd-moss'
                        "
                    >
                        {{ message }}
                    </p>
                </div>
            </PageSectionCard>

            <PageSectionCard
                v-for="section in sections"
                :key="section.meal_type"
                padding="none"
                :aria-label="section.label"
            >
                <div
                    class="flex items-center justify-between border-b border-cd-line px-5 py-3"
                >
                    <div>
                        <h2 class="font-sans text-base font-semibold text-cd-ink">
                            {{ section.label }}
                        </h2>
                        <p class="font-sans text-xs text-cd-ink-muted">
                            {{ formatNum(section.subtotal.kcal) }} kcal · P
                            {{ formatNum(section.subtotal.protein_g) }} / F
                            {{ formatNum(section.subtotal.fat_g) }} / C
                            {{ formatNum(section.subtotal.carb_g) }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        class="font-sans"
                        @click="openAddEntry(section.meal_type)"
                    >
                        <Plus :size="14" :stroke-width="1.6" />
                        追加
                    </Button>
                </div>

                <ul v-if="section.entries.length > 0" class="flex flex-col">
                    <li
                        v-for="entry in section.entries"
                        :key="entry.id"
                        class="flex items-start justify-between gap-3 border-b border-cd-line px-5 py-3 last:border-b-0"
                    >
                        <div class="min-w-0">
                            <p class="font-sans text-sm font-semibold text-cd-ink">
                                {{ entry.name }}
                                <span class="font-normal text-cd-ink-muted">
                                    × {{ formatNum(entry.quantity) }}
                                </span>
                            </p>
                            <p class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                                {{ formatNum(entry.kcal) }} kcal · P
                                {{ formatNum(entry.protein_g) }} / F
                                {{ formatNum(entry.fat_g) }} / C
                                {{ formatNum(entry.carb_g) }}
                            </p>
                            <p
                                v-if="entry.note"
                                class="mt-1 font-sans text-xs text-cd-ink-muted"
                            >
                                {{ entry.note }}
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
                <p
                    v-else
                    class="px-5 py-4 font-sans text-sm text-cd-ink-muted"
                >
                    まだ記録がありません。
                </p>
            </PageSectionCard>

            <PageSectionCard aria-label="推移">
                <div class="flex flex-col gap-4">
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
            </PageSectionCard>
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
                <ul
                    class="max-h-40 overflow-y-auto rounded-lg border border-cd-line"
                >
                    <li
                        v-for="food in foodResults"
                        :key="food.id"
                        class="cursor-pointer border-b border-cd-line px-3 py-2 last:border-b-0 hover:bg-muted/40"
                        :class="
                            selectedFood?.id === food.id
                                ? 'bg-primary/5'
                                : ''
                        "
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
