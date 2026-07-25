<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Camera,
    ChevronRight,
    Coffee,
    Cookie,
    Copy,
    Leaf,
    Pencil,
    Plus,
    ScanBarcode,
    Search,
    Star,
    Store,
    Sun,
    Trash2,
    Utensils,
} from '@lucide/vue';
import type { EChartsCoreOption } from 'echarts/core';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import BarcodeLookupModal from '@/components/BarcodeLookupModal.vue';
import DateNavigator from '@/components/DateNavigator.vue';
import MealsSettingsPanel from '@/components/meals/MealsSettingsPanel.vue';
import MealsTodayHero from '@/components/meals/MealsTodayHero.vue';
import MealsTrendsPanel from '@/components/meals/MealsTrendsPanel.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTabShell from '@/components/PageTabShell.vue';
import PageViewTabs from '@/components/PageViewTabs.vue';
import RestaurantLookupModal from '@/components/RestaurantLookupModal.vue';
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
const usualFavorites = ref<FoodItem[]>([]);
const usualRecent = ref<FoodItem[]>([]);
const usualFrequent = ref<FoodItem[]>([]);
const showUsualSections = ref(false);
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
const showCopyModal = ref(false);
const copyingEntry = ref<MealEntry | null>(null);
const copyForm = ref({
    eaten_on: '',
    meal_type: 'breakfast' as MealSection['meal_type'],
    quantity: '1',
});

const showBarcodeModal = ref(false);
const showRestaurantModal = ref(false);
const restaurantInitialStep = ref<'photo_capture' | 'menu_input' | undefined>(undefined);

const entryPreview = computed(() => {
    const q = Number(entryForm.value.quantity) || 0;
    const baseKcal = Number(
        selectedFood.value?.kcal ?? entryForm.value.kcal ?? 0,
    );
    const baseP = Number(
        selectedFood.value?.protein_g ?? entryForm.value.protein_g ?? 0,
    );
    const baseF = Number(selectedFood.value?.fat_g ?? entryForm.value.fat_g ?? 0);
    const baseC = Number(
        selectedFood.value?.carb_g ?? entryForm.value.carb_g ?? 0,
    );

    return {
        label: selectedFood.value?.serving_label || '1サービング',
        kcal: Math.round(baseKcal * q * 10) / 10,
        protein_g: Math.round(baseP * q * 10) / 10,
        fat_g: Math.round(baseF * q * 10) / 10,
        carb_g: Math.round(baseC * q * 10) / 10,
    };
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

const goalAmounts = computed(() => {
    if (!props.goal) {
        return null;
    }

    return {
        protein_g: Number(props.goal.protein_g),
        fat_g: Number(props.goal.fat_g),
        carb_g: Number(props.goal.carb_g),
    };
});

const mealTypeMeta: Record<
    MealSection['meal_type'],
    { icon: Component; className: string }
> = {
    breakfast: {
        icon: Coffee,
        className: 'bg-amber-50 text-amber-600',
    },
    lunch: {
        icon: Sun,
        className: 'bg-sky-50 text-sky-600',
    },
    snack: {
        icon: Cookie,
        className: 'bg-violet-50 text-violet-600',
    },
    dinner: {
        icon: Leaf,
        className: 'bg-emerald-50 text-emerald-600',
    },
};

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
    usualFavorites.value = [];
    usualRecent.value = [];
    usualFrequent.value = [];
    showUsualSections.value = false;
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
    showUsualSections.value = false;
    void searchFoods('');
}

function openUsualMeals(): void {
    openAddEntry();
    entryTab.value = 'food';
    showUsualSections.value = true;
    void loadUsualFoods();
}

function openBarcodeScanner(): void {
    showBarcodeModal.value = true;
}

function openPhotoEstimate(): void {
    restaurantInitialStep.value = 'photo_capture';
    showRestaurantModal.value = true;
}

function openMenuEstimate(): void {
    restaurantInitialStep.value = 'menu_input';
    showRestaurantModal.value = true;
}

function onRestaurantRegistered(food: FoodItem): void {
    message.value = `「${food.name}」をマイ食品に登録しました。`;
    router.reload({ only: ['sections', 'totals', 'chartPoints', 'goal'] });
}

function onBarcodeRegistered(food: FoodItem): void {
    message.value = `「${food.name}」をマイ食品に登録しました。`;
    router.reload({ only: ['sections', 'totals', 'chartPoints', 'goal'] });
}

function onMealAdded(payload: { food: FoodItem | null; entry: MealEntry }): void {
    const name = payload.entry.name;
    message.value = `「${name}」を食事に追加しました。`;
    router.reload({ only: ['sections', 'totals', 'chartPoints', 'goal'] });
}

function onBarcodeHit(food: FoodItem): void {
    selectFood(food);
    entryMealType.value = nextMealType();
    showUsualSections.value = false;
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

const quickActions = [
    {
        key: 'usual',
        title: 'いつもの食事',
        description: 'お気に入り・最近・よく使う',
        icon: Utensils,
        run: openUsualMeals,
    },
    {
        key: 'barcode',
        title: 'バーコード',
        description: '市販商品を初回でも検索',
        icon: ScanBarcode,
        run: openBarcodeScanner,
    },
    {
        key: 'photo',
        title: '料理の写真',
        description: '料理をAI推定',
        icon: Camera,
        run: openPhotoEstimate,
    },
    {
        key: 'menu',
        title: '外食メニュー',
        description: '店名とメニュー名から推定',
        icon: Store,
        run: openMenuEstimate,
    },
    {
        key: 'search',
        title: 'マイ食品を検索',
        description: '登録済みの食品から探す',
        icon: Search,
        run: openQuickFoodSearch,
    },
    {
        key: 'copy',
        title: '昨日からコピー',
        description: '前日の食事をまとめて追加',
        icon: Copy,
        run: copyPreviousDay,
    },
] as const;

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
        if (query.trim() !== '') {
            showUsualSections.value = false;
        }
    } catch {
        foodResults.value = [];
    }
}

async function loadUsualFoods(): Promise<void> {
    try {
        const data = await apiFetch<{
            favorites: FoodItem[];
            recent: FoodItem[];
            frequent: FoodItem[];
        }>('/meals/foods?group=usual');
        usualFavorites.value = data.favorites;
        usualRecent.value = data.recent;
        usualFrequent.value = data.frequent;
    } catch {
        usualFavorites.value = [];
        usualRecent.value = [];
        usualFrequent.value = [];
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

async function toggleFavorite(food: FoodItem, event: Event): Promise<void> {
    event.stopPropagation();
    const next = !(food.is_favorite ?? false);

    try {
        const data = await apiFetch<{ food: FoodItem }>(
            `/meals/foods/${food.id}/favorite`,
            {
                method: 'PATCH',
                body: JSON.stringify({ is_favorite: next }),
            },
        );

        const apply = (list: FoodItem[]): FoodItem[] =>
            list.map((item) =>
                item.id === data.food.id
                    ? { ...item, is_favorite: data.food.is_favorite }
                    : item,
            );

        foodResults.value = apply(foodResults.value);
        usualFavorites.value = apply(usualFavorites.value);
        usualRecent.value = apply(usualRecent.value);
        usualFrequent.value = apply(usualFrequent.value);

        if (selectedFood.value?.id === data.food.id) {
            selectedFood.value = {
                ...selectedFood.value,
                is_favorite: data.food.is_favorite,
            };
        }

        if (showUsualSections.value) {
            void loadUsualFoods();
        }
    } catch {
        message.value = 'お気に入りの更新に失敗しました。';
    }
}

function openCopyEntry(entry: MealEntry): void {
    copyingEntry.value = entry;
    copyForm.value = {
        eaten_on: props.date,
        meal_type: entry.meal_type,
        quantity: entry.quantity,
    };
    showCopyModal.value = true;
}

async function copyEntry(): Promise<void> {
    if (!copyingEntry.value) {
        return;
    }

    saving.value = true;
    message.value = null;

    try {
        await apiFetch(`/meals/${copyingEntry.value.id}/copy`, {
            method: 'POST',
            body: JSON.stringify({
                eaten_on: copyForm.value.eaten_on,
                meal_type: copyForm.value.meal_type,
                quantity: Number(copyForm.value.quantity),
            }),
        });
        showCopyModal.value = false;
        message.value = `「${copyingEntry.value.name}」をコピーしました。`;
        router.reload({ only: ['sections', 'totals', 'chartPoints', 'goal'] });
    } catch {
        message.value = 'コピーに失敗しました。';
    } finally {
        saving.value = false;
    }
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
            <PageTabShell
                title="食事記録"
                subtitle="残り摂取と次の一手を先に、記録は下で"
                :back-href="`/records?date=${date}`"
                back-label="パフォーマンス管理"
            >
                <template #calendar>
                    <DateNavigator
                        compact
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
                </template>
                <template #tabs>
                    <PageViewTabs
                        v-model="activeTab"
                        :tabs="viewTabs"
                        aria-label="食事記録表示切替"
                    />
                </template>

                <MealsTodayHero
                    v-show="activeTab === 'today'"
                    id="panel-today"
                    role="tabpanel"
                    :remaining="remaining"
                    :totals-kcal="totals.kcal"
                    :goal-kcal="goal ? Number(goal.kcal) : null"
                    :kcal-achievement="kcalAchievement"
                    :goal-amounts="goalAmounts"
                    :next-food-hint="nextFoodHint"
                    @set-goal="
                        activeTab = 'settings';
                        openGoalModal();
                    "
                />
                <MealsTrendsPanel
                    v-show="activeTab === 'trends'"
                    id="panel-trends"
                    role="tabpanel"
                    v-model:filter-from="filterFrom"
                    v-model:filter-to="filterTo"
                    :has-chart-data="hasChartData"
                    :kcal-chart-option="kcalChartOption"
                    :pfc-chart-option="pfcChartOption"
                    @apply="applyChartFilter"
                />
                <MealsSettingsPanel
                    v-show="activeTab === 'settings'"
                    id="panel-settings"
                    role="tabpanel"
                    :goal="goal"
                    @edit-goal="openGoalModal"
                />
            </PageTabShell>

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

            <!-- 今日（二次ブロック） -->
            <div
                v-show="activeTab === 'today'"
                class="flex flex-col gap-4"
            >
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="action in quickActions"
                        :key="action.key"
                        type="button"
                        class="group flex items-center gap-3 rounded-2xl border border-cd-line bg-white px-4 py-3.5 text-left shadow-sm transition-colors hover:border-primary/35 hover:bg-[#F8F6FC]"
                        :disabled="action.key === 'copy' && saving"
                        @click="action.run()"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#F3F1F8] text-primary"
                        >
                            <component
                                :is="action.icon"
                                :size="18"
                                :stroke-width="1.7"
                            />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span
                                class="block font-sans text-sm font-semibold text-cd-ink"
                            >
                                {{ action.title }}
                            </span>
                            <span
                                class="mt-0.5 block font-sans text-xs text-cd-ink-muted"
                            >
                                {{ action.description }}
                            </span>
                        </span>
                        <ChevronRight
                            :size="16"
                            :stroke-width="1.7"
                            class="shrink-0 text-cd-ink-muted/70 transition-colors group-hover:text-primary"
                        />
                    </button>
                </div>

                <PageSectionCard padding="none" aria-label="今日の食事記録">
                    <div
                        class="flex items-center justify-between gap-3 border-b border-cd-line px-5 py-4"
                    >
                        <h2
                            class="font-sans text-base font-semibold text-cd-ink"
                        >
                            今日の食事記録
                        </h2>
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

                    <div
                        v-if="recordedEntries.length > 0"
                        class="overflow-x-auto"
                    >
                        <table class="w-full min-w-[40rem] border-collapse text-left">
                            <thead>
                                <tr
                                    class="border-b border-cd-line bg-[#FAFAFC] font-sans text-[11px] font-medium text-cd-ink-muted"
                                >
                                    <th class="px-5 py-3 font-medium">食事</th>
                                    <th class="px-3 py-3 font-medium">
                                        メニュー・食品
                                    </th>
                                    <th
                                        class="px-3 py-3 text-right font-medium"
                                    >
                                        エネルギー (kcal)
                                    </th>
                                    <th
                                        class="px-3 py-3 text-right font-medium"
                                    >
                                        P (g)
                                    </th>
                                    <th
                                        class="px-3 py-3 text-right font-medium"
                                    >
                                        F (g)
                                    </th>
                                    <th
                                        class="px-3 py-3 text-right font-medium"
                                    >
                                        C (g)
                                    </th>
                                    <th
                                        class="px-5 py-3 text-right font-medium"
                                    >
                                        操作
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="entry in recordedEntries"
                                    :key="entry.id"
                                    class="border-b border-cd-line/80"
                                >
                                    <td class="px-5 py-3.5">
                                        <span
                                            class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink"
                                        >
                                            <span
                                                class="flex size-7 items-center justify-center rounded-full"
                                                :class="
                                                    mealTypeMeta[entry.meal_type]
                                                        .className
                                                "
                                            >
                                                <component
                                                    :is="
                                                        mealTypeMeta[
                                                            entry.meal_type
                                                        ].icon
                                                    "
                                                    :size="13"
                                                    :stroke-width="1.8"
                                                />
                                            </span>
                                            {{ entry.sectionLabel }}
                                        </span>
                                    </td>
                                    <td
                                        class="max-w-[16rem] truncate px-3 py-3.5 font-sans text-sm text-cd-ink"
                                    >
                                        {{ entry.name }}
                                        <span
                                            class="text-cd-ink-muted"
                                        >
                                            × {{ formatNum(entry.quantity) }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-3 py-3.5 text-right font-sans text-sm text-cd-ink"
                                    >
                                        {{ formatNum(entry.kcal) }}
                                    </td>
                                    <td
                                        class="px-3 py-3.5 text-right font-sans text-sm text-cd-ink"
                                    >
                                        {{ formatNum(entry.protein_g) }}
                                    </td>
                                    <td
                                        class="px-3 py-3.5 text-right font-sans text-sm text-cd-ink"
                                    >
                                        {{ formatNum(entry.fat_g) }}
                                    </td>
                                    <td
                                        class="px-3 py-3.5 text-right font-sans text-sm text-cd-ink"
                                    >
                                        {{ formatNum(entry.carb_g) }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div
                                            class="flex justify-end gap-1"
                                        >
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                :aria-label="`${entry.name} をコピー`"
                                                @click="openCopyEntry(entry)"
                                            >
                                                <Copy
                                                    :size="14"
                                                    :stroke-width="1.6"
                                                />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                :aria-label="`${entry.name} を編集`"
                                                @click="openEditEntry(entry)"
                                            >
                                                <Pencil
                                                    :size="14"
                                                    :stroke-width="1.6"
                                                />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                :aria-label="`${entry.name} を削除`"
                                                @click="deleteEntry(entry)"
                                            >
                                                <Trash2
                                                    :size="14"
                                                    :stroke-width="1.6"
                                                />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-[#FAFAFC]">
                                    <td
                                        colspan="2"
                                        class="px-5 py-3.5 font-sans text-sm font-semibold text-cd-ink"
                                    >
                                        合計
                                    </td>
                                    <td
                                        class="px-3 py-3.5 text-right font-sans text-sm font-semibold text-cd-ink"
                                    >
                                        {{ formatNum(totals.kcal) }}
                                    </td>
                                    <td
                                        class="px-3 py-3.5 text-right font-sans text-sm font-semibold text-cd-ink"
                                    >
                                        {{ formatNum(totals.protein_g) }}
                                    </td>
                                    <td
                                        class="px-3 py-3.5 text-right font-sans text-sm font-semibold text-cd-ink"
                                    >
                                        {{ formatNum(totals.fat_g) }}
                                    </td>
                                    <td
                                        class="px-3 py-3.5 text-right font-sans text-sm font-semibold text-cd-ink"
                                    >
                                        {{ formatNum(totals.carb_g) }}
                                    </td>
                                    <td class="px-5 py-3.5" />
                                </tr>
                            </tfoot>
                        </table>
                    </div>

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
        :date="date"
        :default-meal-type="nextMealType()"
        @food-registered="onBarcodeRegistered"
        @food-hit="onBarcodeHit"
        @meal-added="onMealAdded"
    />

    <RestaurantLookupModal
        v-model:open="showRestaurantModal"
        :date="date"
        :default-meal-type="nextMealType()"
        :initial-step="restaurantInitialStep"
        @food-registered="onRestaurantRegistered"
        @food-hit="onBarcodeHit"
        @meal-added="onMealAdded"
    />

    <Dialog :open="showEntryModal" @update:open="(v) => (showEntryModal = v)">
        <DialogContent class="bg-cd-surface sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="font-sans">
                    {{ editingEntry ? '食事を編集' : '食事を追加' }}
                </DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    登録済みの食品から探すか、直接入力できます。数量はサービング倍率です。
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
                    <Label class="font-sans text-xs">マイ食品を検索</Label>
                    <Input
                        :model-value="foodQuery"
                        type="text"
                        placeholder="登録済みの食品から探す"
                        @update:model-value="onFoodQueryInput"
                    />
                </div>

                <div
                    v-if="showUsualSections && foodQuery.trim() === ''"
                    class="max-h-56 space-y-3 overflow-y-auto"
                >
                    <div v-if="usualFavorites.length > 0">
                        <p class="mb-1 font-sans text-xs font-semibold text-cd-ink-muted">
                            お気に入り
                        </p>
                        <ul class="rounded-lg border border-cd-line">
                            <li
                                v-for="food in usualFavorites"
                                :key="`fav-${food.id}`"
                                class="flex cursor-pointer items-center gap-2 border-b border-cd-line px-3 py-2 last:border-b-0 hover:bg-muted/40"
                                :class="selectedFood?.id === food.id ? 'bg-primary/5' : ''"
                                @click="selectFood(food)"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="font-sans text-sm font-medium text-cd-ink">
                                        {{ food.name }}
                                    </p>
                                    <p class="font-sans text-xs text-cd-ink-muted">
                                        {{ food.serving_label }} ·
                                        {{ formatNum(food.kcal) }} kcal · P
                                        {{ formatNum(food.protein_g) }} / F
                                        {{ formatNum(food.fat_g) }} / C
                                        {{ formatNum(food.carb_g) }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 text-primary"
                                    :aria-label="`${food.name} のお気に入りを切替`"
                                    @click="toggleFavorite(food, $event)"
                                >
                                    <Star
                                        :size="16"
                                        :fill="food.is_favorite ? 'currentColor' : 'none'"
                                    />
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div v-if="usualRecent.length > 0">
                        <p class="mb-1 font-sans text-xs font-semibold text-cd-ink-muted">
                            最近使った
                        </p>
                        <ul class="rounded-lg border border-cd-line">
                            <li
                                v-for="food in usualRecent"
                                :key="`recent-${food.id}`"
                                class="flex cursor-pointer items-center gap-2 border-b border-cd-line px-3 py-2 last:border-b-0 hover:bg-muted/40"
                                :class="selectedFood?.id === food.id ? 'bg-primary/5' : ''"
                                @click="selectFood(food)"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="font-sans text-sm font-medium text-cd-ink">
                                        {{ food.name }}
                                    </p>
                                    <p class="font-sans text-xs text-cd-ink-muted">
                                        {{ food.serving_label }} ·
                                        {{ formatNum(food.kcal) }} kcal
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 text-primary"
                                    :aria-label="`${food.name} のお気に入りを切替`"
                                    @click="toggleFavorite(food, $event)"
                                >
                                    <Star
                                        :size="16"
                                        :fill="food.is_favorite ? 'currentColor' : 'none'"
                                    />
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div v-if="usualFrequent.length > 0">
                        <p class="mb-1 font-sans text-xs font-semibold text-cd-ink-muted">
                            よく使う
                        </p>
                        <ul class="rounded-lg border border-cd-line">
                            <li
                                v-for="food in usualFrequent"
                                :key="`freq-${food.id}`"
                                class="flex cursor-pointer items-center gap-2 border-b border-cd-line px-3 py-2 last:border-b-0 hover:bg-muted/40"
                                :class="selectedFood?.id === food.id ? 'bg-primary/5' : ''"
                                @click="selectFood(food)"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="font-sans text-sm font-medium text-cd-ink">
                                        {{ food.name }}
                                    </p>
                                    <p class="font-sans text-xs text-cd-ink-muted">
                                        {{ food.serving_label }} ·
                                        {{ formatNum(food.kcal) }} kcal
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 text-primary"
                                    :aria-label="`${food.name} のお気に入りを切替`"
                                    @click="toggleFavorite(food, $event)"
                                >
                                    <Star
                                        :size="16"
                                        :fill="food.is_favorite ? 'currentColor' : 'none'"
                                    />
                                </button>
                            </li>
                        </ul>
                    </div>
                    <p
                        v-if="
                            usualFavorites.length === 0 &&
                            usualRecent.length === 0 &&
                            usualFrequent.length === 0
                        "
                        class="px-1 py-3 font-sans text-sm text-cd-ink-muted"
                    >
                        まだいつもの食事がありません。バーコードや検索から追加してください。
                    </p>
                </div>

                <ul
                    v-else
                    class="max-h-40 overflow-y-auto rounded-lg border border-cd-line"
                >
                    <li
                        v-for="food in foodResults"
                        :key="food.id"
                        class="flex cursor-pointer items-center gap-2 border-b border-cd-line px-3 py-2 last:border-b-0 hover:bg-muted/40"
                        :class="selectedFood?.id === food.id ? 'bg-primary/5' : ''"
                        @click="selectFood(food)"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-sans text-sm font-medium text-cd-ink">
                                {{ food.name }}
                            </p>
                            <p class="font-sans text-xs text-cd-ink-muted">
                                {{ food.serving_label }} ·
                                {{ formatNum(food.kcal) }} kcal · P
                                {{ formatNum(food.protein_g) }} / F
                                {{ formatNum(food.fat_g) }} / C
                                {{ formatNum(food.carb_g) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-primary"
                            :aria-label="`${food.name} のお気に入りを切替`"
                            @click="toggleFavorite(food, $event)"
                        >
                            <Star
                                :size="16"
                                :fill="food.is_favorite ? 'currentColor' : 'none'"
                            />
                        </button>
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
                    <p class="font-sans text-[11px] text-cd-ink-muted">
                        {{ entryPreview.label }} × {{ entryForm.quantity }}
                    </p>
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">メモ</Label>
                    <Input v-model="entryForm.note" type="text" maxlength="500" />
                </div>
                <div
                    class="col-span-2 rounded-lg bg-muted/40 px-3 py-2 font-sans text-xs text-cd-ink-muted"
                >
                    記録予定:
                    {{ formatNum(entryPreview.kcal) }} kcal · P
                    {{ formatNum(entryPreview.protein_g) }}g · F
                    {{ formatNum(entryPreview.fat_g) }}g · C
                    {{ formatNum(entryPreview.carb_g) }}g
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

    <Dialog :open="showCopyModal" @update:open="(v) => (showCopyModal = v)">
        <DialogContent class="bg-cd-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="font-sans">食事をコピー</DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    「{{ copyingEntry?.name }}」を指定日・区分へコピーします。元の記録は変更されません。
                </DialogDescription>
            </DialogHeader>
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2 flex flex-col gap-1">
                    <Label class="font-sans text-xs">コピー先の日付</Label>
                    <Input v-model="copyForm.eaten_on" type="date" />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">食事区分</Label>
                    <select
                        v-model="copyForm.meal_type"
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
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">数量</Label>
                    <Input
                        v-model="copyForm.quantity"
                        type="number"
                        min="0.1"
                        max="100"
                        step="0.1"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    class="font-sans"
                    @click="showCopyModal = false"
                >
                    キャンセル
                </Button>
                <Button
                    type="button"
                    class="font-sans"
                    :disabled="saving || !copyingEntry"
                    @click="copyEntry"
                >
                    コピー
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
