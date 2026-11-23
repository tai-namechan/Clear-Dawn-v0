<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Camera,
    ChevronDown,
    ChevronRight,
    Coffee,
    Cookie,
    Copy,
    Download,
    Leaf,
    MoreVertical,
    Plus,
    ScanBarcode,
    Search,
    Star,
    Store,
    Sun,
    Utensils,
} from '@lucide/vue';
import type { EChartsCoreOption } from 'echarts/core';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import BarcodeLookupModal from '@/components/BarcodeLookupModal.vue';
import CdProgressRing from '@/components/CdProgressRing.vue';
import DateNavigator from '@/components/DateNavigator.vue';
import MealsSettingsPanel from '@/components/meals/MealsSettingsPanel.vue';
import MealsTrendsPanel from '@/components/meals/MealsTrendsPanel.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTabShell from '@/components/PageTabShell.vue';
import PageViewTabs from '@/components/PageViewTabs.vue';
import BodyStoryExportButton from '@/components/records/BodyStoryExportButton.vue';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { apiFetch } from '@/lib/apiFetch';
import {
    CHART_COLORS,
    chartAxisLabel,
    chartAxisLine,
    chartLegend,
    chartLineSeriesStyle,
    chartSplitLine,
    nutritionSeriesByDate,
} from '@/lib/chartTheme';
import { kcalAchievement as calculateKcalAchievement } from '@/lib/kcalAchievement.mjs';
import { hapticSuccess } from '@/lib/native/haptics';
import { PFC_COLORS } from '@/lib/pfcColors';
import { nutrientProgress } from '@/lib/pfcShare.mjs';
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
const previewingEntry = ref<MealEntry | null>(null);
const copyForm = ref({
    eaten_on: '',
    meal_type: 'breakfast' as MealSection['meal_type'],
    quantity: '1',
});

const showBarcodeModal = ref(false);
const showRestaurantModal = ref(false);
const restaurantInitialStep = ref<'photo_capture' | 'menu_input' | undefined>(
    undefined,
);

const entryPreview = computed(() => {
    const q = Number(entryForm.value.quantity) || 0;
    const baseKcal = Number(
        selectedFood.value?.kcal ?? entryForm.value.kcal ?? 0,
    );
    const baseP = Number(
        selectedFood.value?.protein_g ?? entryForm.value.protein_g ?? 0,
    );
    const baseF = Number(
        selectedFood.value?.fat_g ?? entryForm.value.fat_g ?? 0,
    );
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

const achievement = computed(() =>
    calculateKcalAchievement(props.totals.kcal, props.goal?.kcal),
);

const pfcSummary = computed(() =>
    [
        {
            key: 'p',
            letter: 'P',
            label: 'たんぱく質',
            grams: props.totals.protein_g,
            goal: props.goal ? Number(props.goal.protein_g) : null,
        },
        {
            key: 'f',
            letter: 'F',
            label: '脂質',
            grams: props.totals.fat_g,
            goal: props.goal ? Number(props.goal.fat_g) : null,
        },
        {
            key: 'c',
            letter: 'C',
            label: '炭水化物',
            grams: props.totals.carb_g,
            goal: props.goal ? Number(props.goal.carb_g) : null,
        },
    ].map((row) => ({
        ...row,
        progress: nutrientProgress(row.grams, row.goal),
    })),
);

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

const kcalChartOption = computed<EChartsCoreOption>(() => {
    const { dates, values } = nutritionSeriesByDate(
        props.chartPoints,
        filterFrom.value,
        filterTo.value,
        'kcal',
    );

    return {
        grid: { left: 48, right: 24, top: 24, bottom: 32 },
        tooltip: { trigger: 'axis' },
        xAxis: {
            type: 'category',
            data: dates,
            axisLabel: chartAxisLabel(11),
            axisLine: chartAxisLine(),
        },
        yAxis: {
            type: 'value',
            axisLabel: chartAxisLabel(11),
            splitLine: chartSplitLine(),
        },
        series: [
            {
                name: 'kcal',
                type: 'line',
                smooth: true,
                ...chartLineSeriesStyle(CHART_COLORS.primary),
                data: values,
                areaStyle: { color: CHART_COLORS.primaryArea },
            },
        ],
    };
});

const pfcChartOption = computed<EChartsCoreOption>(() => {
    const protein = nutritionSeriesByDate(
        props.chartPoints,
        filterFrom.value,
        filterTo.value,
        'protein_g',
    );
    const fat = nutritionSeriesByDate(
        props.chartPoints,
        filterFrom.value,
        filterTo.value,
        'fat_g',
    );
    const carb = nutritionSeriesByDate(
        props.chartPoints,
        filterFrom.value,
        filterTo.value,
        'carb_g',
    );

    return {
        grid: { left: 48, right: 24, top: 40, bottom: 32 },
        tooltip: { trigger: 'axis' },
        legend: chartLegend(11),
        xAxis: {
            type: 'category',
            data: protein.dates,
            axisLabel: chartAxisLabel(11),
            axisLine: chartAxisLine(),
        },
        yAxis: {
            type: 'value',
            axisLabel: chartAxisLabel(11),
            splitLine: chartSplitLine(),
        },
        series: [
            {
                name: 'P',
                type: 'bar',
                stack: 'pfc',
                barMaxWidth: 36,
                data: protein.values,
                itemStyle: { color: PFC_COLORS.p.hex },
            },
            {
                name: 'F',
                type: 'bar',
                stack: 'pfc',
                barMaxWidth: 36,
                data: fat.values,
                itemStyle: { color: PFC_COLORS.f.hex },
            },
            {
                name: 'C',
                type: 'bar',
                stack: 'pfc',
                barMaxWidth: 36,
                data: carb.values,
                itemStyle: {
                    color: PFC_COLORS.c.hex,
                    borderRadius: [4, 4, 0, 0],
                },
            },
        ],
    };
});

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
    const empty = props.sections.find(
        (section) => section.entries.length === 0,
    );

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

function onMealAdded(payload: {
    food: FoodItem | null;
    entry: MealEntry;
}): void {
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

/** Mockup: three large tiles for the most common ways to log a meal. */
const quickTiles = [
    {
        key: 'barcode',
        title: 'バーコード',
        subtitle: 'スキャン',
        icon: ScanBarcode,
        run: openBarcodeScanner,
    },
    {
        key: 'search',
        title: '食品を',
        subtitle: '検索',
        icon: Search,
        run: openQuickFoodSearch,
    },
    {
        key: 'photo',
        title: '写真で',
        subtitle: '栄養登録',
        icon: Camera,
        run: openPhotoEstimate,
    },
] as const;

/** Shortcut rows under the tiles. */
const quickRows = [
    {
        key: 'usual',
        title: 'いつもの食事',
        icon: Utensils,
        run: openUsualMeals,
    },
    {
        key: 'copy',
        title: '前日の食事をコピー',
        icon: Copy,
        run: copyPreviousDay,
    },
    {
        key: 'menu',
        title: '外食メニューから推定',
        icon: Store,
        run: openMenuEstimate,
    },
    {
        key: 'manual',
        title: '食事を手入力',
        icon: Plus,
        run: () => openAddEntry(),
    },
] as const;

const openMealSections = ref<MealSection['meal_type'][]>([]);

/** Breakfast / lunch / dinner always show; snacks only when recorded. */
const visibleSections = computed(() =>
    props.sections.filter(
        (section) =>
            section.meal_type !== 'snack' || section.entries.length > 0,
    ),
);

function isMealSectionOpen(mealType: MealSection['meal_type']): boolean {
    return openMealSections.value.includes(mealType);
}

function onMealSectionClick(section: MealSection): void {
    if (section.entries.length === 0) {
        openAddEntry(section.meal_type);

        return;
    }

    openMealSections.value = isMealSectionOpen(section.meal_type)
        ? openMealSections.value.filter((type) => type !== section.meal_type)
        : [...openMealSections.value, section.meal_type];
}

function sectionThumb(section: MealSection): string | null {
    return (
        section.entries.find((entry) => entry.has_photo && entry.photo_url)
            ?.photo_url ?? null
    );
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

function openPhotoPreview(entry: MealEntry): void {
    if (!entry.has_photo || !entry.photo_url) {
        return;
    }

    previewingEntry.value = entry;
}

function photoDownloadUrl(entry: MealEntry | null): string {
    if (!entry?.photo_url) {
        return '#';
    }

    return `${entry.photo_url}?download=1`;
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
        void hapticSuccess();
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
        <div
            class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-4 md:gap-5"
        >
            <PageTabShell
                title="食事"
                :back-href="`/records?date=${date}`"
                back-label="パフォーマンス管理"
            >
                <template #hero>
                    <div class="cd-meal-hero">
                        <div class="flex items-center gap-4">
                            <CdProgressRing
                                :percent="achievement.ringPercent"
                                size="xl"
                                :label="
                                    achievement.percent === null
                                        ? `本日の摂取 ${formatNum(totals.kcal)} kcal`
                                        : `本日の摂取 ${formatNum(totals.kcal)} kcal、目標の ${achievement.percent}%`
                                "
                            >
                                <span class="cd-meal-ring-caption"
                                    >本日の摂取</span
                                >
                                <span class="cd-meal-ring-value"
                                    >{{ formatNum(totals.kcal)
                                    }}<small>kcal</small></span
                                >
                                <span class="cd-meal-ring-caption">{{
                                    goal
                                        ? `/ ${formatNum(goal.kcal)} kcal`
                                        : '目標未設定'
                                }}</span>
                            </CdProgressRing>
                            <div class="ml-auto">
                                <p
                                    v-if="achievement.status === 'over'"
                                    class="cd-meal-remaining cd-meal-remaining--over"
                                >
                                    <span>超過</span>
                                    <strong
                                        >+{{
                                            formatNum(
                                                achievement.difference ?? 0,
                                            )
                                        }}<small>kcal</small></strong
                                    >
                                </p>
                                <p
                                    v-else-if="achievement.status !== 'unset'"
                                    class="cd-meal-remaining"
                                >
                                    <span>残り</span>
                                    <strong
                                        >{{
                                            formatNum(
                                                Math.abs(
                                                    achievement.difference ?? 0,
                                                ),
                                            )
                                        }}<small>kcal</small></strong
                                    >
                                </p>
                                <Button
                                    v-else
                                    type="button"
                                    size="sm"
                                    class="rounded-full font-sans"
                                    @click="openGoalModal"
                                >
                                    目標を設定
                                </Button>
                            </div>
                        </div>
                        <ul
                            class="mt-4 grid grid-cols-3 gap-3"
                            aria-label="PFC"
                        >
                            <li v-for="row in pfcSummary" :key="row.key">
                                <p
                                    class="flex items-center gap-1.5 font-sans text-[11px] text-cd-ink-muted"
                                >
                                    <span
                                        :class="`cd-pfc-letter cd-pfc-letter--${row.key}`"
                                        >{{ row.letter }}</span
                                    >
                                    {{ row.label }}
                                </p>
                                <p
                                    class="mt-1 font-sans text-base font-bold whitespace-nowrap text-cd-ink tabular-nums"
                                >
                                    {{ formatNum(row.grams)
                                    }}<small class="text-xs font-semibold">
                                        g</small
                                    ><span
                                        v-if="row.goal !== null"
                                        class="text-[11px] font-medium text-cd-ink-muted"
                                    >
                                        / {{ formatNum(row.goal) }} g</span
                                    >
                                </p>
                                <div class="cd-pfc-bar">
                                    <span
                                        :class="`cd-pfc-bar-fill cd-pfc-bar-fill--${row.key}`"
                                        :style="{
                                            width: `${row.progress ?? 0}%`,
                                        }"
                                    />
                                </div>
                            </li>
                        </ul>
                    </div>
                </template>
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

            <section
                v-show="activeTab === 'trends'"
                class="cd-panel p-4 md:p-5"
            >
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
            </section>
            <section
                v-show="activeTab === 'settings'"
                class="cd-panel p-4 md:p-5"
            >
                <MealsSettingsPanel
                    v-show="activeTab === 'settings'"
                    id="panel-settings"
                    role="tabpanel"
                    :goal="goal"
                    @edit-goal="openGoalModal"
                />
            </section>

            <!-- 今日: quick logging, then today's meals by type (mockup) -->
            <div v-show="activeTab === 'today'" class="flex flex-col gap-4">
                <div class="grid grid-cols-3 gap-3">
                    <button
                        v-for="tile in quickTiles"
                        :key="tile.key"
                        type="button"
                        class="cd-panel cd-meal-tile"
                        @click="tile.run()"
                    >
                        <component
                            :is="tile.icon"
                            :size="28"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                        <span class="mt-2 block">{{ tile.title }}</span>
                        <span class="block">{{ tile.subtitle }}</span>
                    </button>
                </div>

                <nav class="cd-panel overflow-hidden" aria-label="記録の近道">
                    <button
                        v-for="row in quickRows"
                        :key="row.key"
                        type="button"
                        class="cd-meal-row"
                        :disabled="row.key === 'copy' && saving"
                        @click="row.run()"
                    >
                        <component
                            :is="row.icon"
                            :size="20"
                            :stroke-width="1.7"
                            aria-hidden="true"
                            class="text-[var(--cd-gold)]"
                        />
                        <span class="flex-1 text-left">{{ row.title }}</span>
                        <ChevronRight
                            :size="18"
                            :stroke-width="1.7"
                            aria-hidden="true"
                            class="text-cd-ink-muted/70"
                        />
                    </button>
                </nav>

                <PageSectionCard padding="none" aria-label="今日の食事記録">
                    <div
                        class="flex items-baseline justify-between gap-3 px-5 pt-4 pb-2"
                    >
                        <h2 class="font-sans text-lg font-bold text-cd-ink">
                            本日の食事
                        </h2>
                        <p
                            class="font-sans text-base font-bold text-cd-ink tabular-nums"
                        >
                            {{ formatNum(totals.kcal) }}
                            <span class="text-xs font-medium text-cd-ink-muted"
                                >kcal</span
                            >
                        </p>
                    </div>

                    <ul class="flex flex-col">
                        <li
                            v-for="section in visibleSections"
                            :key="section.meal_type"
                            class="border-t border-cd-line/70"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left"
                                :aria-expanded="
                                    section.entries.length > 0
                                        ? isMealSectionOpen(section.meal_type)
                                        : undefined
                                "
                                @click="onMealSectionClick(section)"
                            >
                                <span
                                    v-if="sectionThumb(section)"
                                    class="size-14 shrink-0 overflow-hidden rounded-xl border border-cd-line bg-cd-surface"
                                >
                                    <img
                                        :src="
                                            sectionThumb(section) ?? undefined
                                        "
                                        alt=""
                                        class="size-full object-cover"
                                    />
                                </span>
                                <span
                                    v-else
                                    class="flex size-14 shrink-0 items-center justify-center rounded-xl"
                                    :class="
                                        mealTypeMeta[section.meal_type]
                                            .className
                                    "
                                    aria-hidden="true"
                                >
                                    <component
                                        :is="
                                            mealTypeMeta[section.meal_type].icon
                                        "
                                        :size="22"
                                        :stroke-width="1.7"
                                    />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block font-sans text-[15px] font-bold text-cd-ink"
                                        >{{ section.label }}</span
                                    >
                                    <span
                                        class="block truncate font-sans text-xs text-cd-ink-muted"
                                        >{{
                                            section.entries.length > 0
                                                ? section.entries
                                                      .map(
                                                          (entry) => entry.name,
                                                      )
                                                      .join('・')
                                                : '未記録 · タップして追加'
                                        }}</span
                                    >
                                </span>
                                <span
                                    class="shrink-0 font-sans text-sm font-semibold text-cd-ink tabular-nums"
                                    >{{
                                        section.entries.length > 0
                                            ? `${formatNum(section.subtotal.kcal)} kcal`
                                            : '—'
                                    }}</span
                                >
                                <ChevronDown
                                    v-if="section.entries.length > 0"
                                    :size="18"
                                    :stroke-width="1.7"
                                    aria-hidden="true"
                                    class="shrink-0 text-cd-ink-muted/70 transition-transform"
                                    :class="{
                                        'rotate-180': isMealSectionOpen(
                                            section.meal_type,
                                        ),
                                    }"
                                />
                                <Plus
                                    v-else
                                    :size="18"
                                    :stroke-width="1.7"
                                    aria-hidden="true"
                                    class="shrink-0 text-cd-ink-muted/70"
                                />
                            </button>

                            <ul
                                v-if="
                                    section.entries.length > 0 &&
                                    isMealSectionOpen(section.meal_type)
                                "
                                class="mx-4 mb-3 flex flex-col rounded-2xl bg-[#f7f5fb]"
                            >
                                <li
                                    v-for="entry in section.entries"
                                    :key="entry.id"
                                    class="flex items-center gap-3 border-b border-white px-3 py-2.5 last:border-b-0"
                                >
                                    <button
                                        v-if="
                                            entry.has_photo && entry.photo_url
                                        "
                                        type="button"
                                        class="size-10 shrink-0 overflow-hidden rounded-lg border border-cd-line bg-cd-surface"
                                        :aria-label="`${entry.name} の写真を見る`"
                                        @click="openPhotoPreview(entry)"
                                    >
                                        <img
                                            :src="entry.photo_url"
                                            :alt="entry.name"
                                            class="size-full object-cover"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="min-w-0 flex-1 text-left"
                                        :aria-label="`${entry.name} を編集`"
                                        @click="openEditEntry(entry)"
                                    >
                                        <span
                                            class="block truncate font-sans text-sm text-cd-ink"
                                        >
                                            {{ entry.name }}
                                            <span class="text-cd-ink-muted"
                                                >×
                                                {{
                                                    formatNum(entry.quantity)
                                                }}</span
                                            >
                                        </span>
                                        <span
                                            class="block font-sans text-[11px] text-cd-ink-muted tabular-nums"
                                            >{{ formatNum(entry.kcal) }} kcal ·
                                            P {{ formatNum(entry.protein_g) }} ·
                                            F {{ formatNum(entry.fat_g) }} · C
                                            {{ formatNum(entry.carb_g) }}</span
                                        >
                                    </button>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-full text-cd-ink-muted hover:bg-white"
                                                :aria-label="`${entry.name} の操作`"
                                            >
                                                <MoreVertical
                                                    :size="18"
                                                    :stroke-width="1.8"
                                                />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                @select="openEditEntry(entry)"
                                                >編集</DropdownMenuItem
                                            >
                                            <DropdownMenuItem
                                                @select="openCopyEntry(entry)"
                                                >コピー</DropdownMenuItem
                                            >
                                            <DropdownMenuItem
                                                class="text-destructive"
                                                @select="deleteEntry(entry)"
                                                >削除</DropdownMenuItem
                                            >
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </li>
                                <li class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 font-sans text-xs font-semibold text-[var(--cd-hero-primary)]"
                                        @click="openAddEntry(section.meal_type)"
                                    >
                                        <Plus :size="14" :stroke-width="1.8" />
                                        {{ section.label }}に追加
                                    </button>
                                </li>
                            </ul>
                        </li>
                    </ul>

                    <div
                        class="flex justify-end border-t border-cd-line/70 px-4 py-3"
                    >
                        <BodyStoryExportButton
                            kind="nutrition"
                            :date="date"
                            label="今日の食事を画像にする"
                        />
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
                    <Input
                        v-model="goalForm.kcal"
                        type="number"
                        min="0"
                        step="1"
                    />
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
                        <p
                            class="mb-1 font-sans text-xs font-semibold text-cd-ink-muted"
                        >
                            お気に入り
                        </p>
                        <ul class="rounded-lg border border-cd-line">
                            <li
                                v-for="food in usualFavorites"
                                :key="`fav-${food.id}`"
                                class="flex cursor-pointer items-center gap-2 border-b border-cd-line px-3 py-2 last:border-b-0 hover:bg-muted/40"
                                :class="
                                    selectedFood?.id === food.id
                                        ? 'bg-primary/5'
                                        : ''
                                "
                                @click="selectFood(food)"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="font-sans text-sm font-medium text-cd-ink"
                                    >
                                        {{ food.name }}
                                    </p>
                                    <p
                                        class="font-sans text-xs text-cd-ink-muted"
                                    >
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
                                        :fill="
                                            food.is_favorite
                                                ? 'currentColor'
                                                : 'none'
                                        "
                                    />
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div v-if="usualRecent.length > 0">
                        <p
                            class="mb-1 font-sans text-xs font-semibold text-cd-ink-muted"
                        >
                            最近使った
                        </p>
                        <ul class="rounded-lg border border-cd-line">
                            <li
                                v-for="food in usualRecent"
                                :key="`recent-${food.id}`"
                                class="flex cursor-pointer items-center gap-2 border-b border-cd-line px-3 py-2 last:border-b-0 hover:bg-muted/40"
                                :class="
                                    selectedFood?.id === food.id
                                        ? 'bg-primary/5'
                                        : ''
                                "
                                @click="selectFood(food)"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="font-sans text-sm font-medium text-cd-ink"
                                    >
                                        {{ food.name }}
                                    </p>
                                    <p
                                        class="font-sans text-xs text-cd-ink-muted"
                                    >
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
                                        :fill="
                                            food.is_favorite
                                                ? 'currentColor'
                                                : 'none'
                                        "
                                    />
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div v-if="usualFrequent.length > 0">
                        <p
                            class="mb-1 font-sans text-xs font-semibold text-cd-ink-muted"
                        >
                            よく使う
                        </p>
                        <ul class="rounded-lg border border-cd-line">
                            <li
                                v-for="food in usualFrequent"
                                :key="`freq-${food.id}`"
                                class="flex cursor-pointer items-center gap-2 border-b border-cd-line px-3 py-2 last:border-b-0 hover:bg-muted/40"
                                :class="
                                    selectedFood?.id === food.id
                                        ? 'bg-primary/5'
                                        : ''
                                "
                                @click="selectFood(food)"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="font-sans text-sm font-medium text-cd-ink"
                                    >
                                        {{ food.name }}
                                    </p>
                                    <p
                                        class="font-sans text-xs text-cd-ink-muted"
                                    >
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
                                        :fill="
                                            food.is_favorite
                                                ? 'currentColor'
                                                : 'none'
                                        "
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
                        :class="
                            selectedFood?.id === food.id ? 'bg-primary/5' : ''
                        "
                        @click="selectFood(food)"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="font-sans text-sm font-medium text-cd-ink"
                            >
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
                                :fill="
                                    food.is_favorite ? 'currentColor' : 'none'
                                "
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
                    <Input
                        v-model="entryForm.name"
                        type="text"
                        maxlength="100"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">kcal</Label>
                    <Input
                        v-model="entryForm.kcal"
                        type="number"
                        min="0"
                        step="0.1"
                    />
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
                    <Label class="font-sans text-xs"
                        >数量（サービング倍率）</Label
                    >
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
                    <Input
                        v-model="entryForm.note"
                        type="text"
                        maxlength="500"
                    />
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
                    「{{
                        copyingEntry?.name
                    }}」を指定日・区分へコピーします。元の記録は変更されません。
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

    <Dialog
        :open="previewingEntry !== null"
        @update:open="(open) => !open && (previewingEntry = null)"
    >
        <DialogContent class="bg-cd-surface sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="font-sans">
                    {{ previewingEntry?.name }}
                </DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    AI解析で使った写真
                </DialogDescription>
            </DialogHeader>
            <div
                v-if="previewingEntry?.photo_url"
                class="overflow-hidden rounded-xl border border-cd-line bg-[#FAFAFC]"
            >
                <img
                    :src="previewingEntry.photo_url"
                    :alt="previewingEntry.name"
                    class="max-h-[70vh] w-full object-contain"
                />
            </div>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    class="font-sans"
                    @click="previewingEntry = null"
                >
                    閉じる
                </Button>
                <Button
                    v-if="previewingEntry?.photo_url"
                    as="a"
                    :href="photoDownloadUrl(previewingEntry)"
                    class="font-sans"
                >
                    <Download :size="14" :stroke-width="1.6" />
                    ダウンロード
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
