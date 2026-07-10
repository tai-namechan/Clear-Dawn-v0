<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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
import type { FoodItem } from '@/types/routine';

interface Props {
    foods: FoodItem[];
    query: string;
}

const props = defineProps<Props>();

const search = ref(props.query);
const showModal = ref(false);
const editing = ref<FoodItem | null>(null);
const saving = ref(false);
const message = ref<string | null>(null);

const form = ref({
    name: '',
    serving_label: '1食分',
    kcal: '',
    protein_g: '',
    fat_g: '',
    carb_g: '',
});

function formatNum(value: string | number): string {
    return Number(value).toLocaleString('ja-JP', {
        maximumFractionDigits: 1,
    });
}

function applySearch(): void {
    router.get(
        '/meals/foods',
        { query: search.value || undefined },
        { preserveState: true, preserveScroll: true },
    );
}

function openCreate(): void {
    editing.value = null;
    form.value = {
        name: '',
        serving_label: '1食分',
        kcal: '',
        protein_g: '',
        fat_g: '',
        carb_g: '',
    };
    showModal.value = true;
}

function openEdit(food: FoodItem): void {
    editing.value = food;
    form.value = {
        name: food.name,
        serving_label: food.serving_label,
        kcal: food.kcal,
        protein_g: food.protein_g,
        fat_g: food.fat_g,
        carb_g: food.carb_g,
    };
    showModal.value = true;
}

async function saveFood(): Promise<void> {
    saving.value = true;
    message.value = null;

    const payload = {
        name: String(form.value.name ?? '').trim(),
        serving_label: String(form.value.serving_label ?? '').trim(),
        kcal: Number(form.value.kcal),
        protein_g: Number(form.value.protein_g),
        fat_g: Number(form.value.fat_g),
        carb_g: Number(form.value.carb_g),
    };

    try {
        if (editing.value) {
            await apiFetch(`/meals/foods/${editing.value.id}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
        } else {
            await apiFetch('/meals/foods', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        }

        showModal.value = false;
        message.value = '保存しました。';
        router.reload({ only: ['foods'] });
    } catch {
        message.value = '保存に失敗しました。';
    } finally {
        saving.value = false;
    }
}

async function deleteFood(food: FoodItem): Promise<void> {
    if (!confirm(`${food.name} を削除しますか？`)) {
        return;
    }

    await apiFetch(`/meals/foods/${food.id}`, { method: 'DELETE' });
    router.reload({ only: ['foods'] });
}
</script>

<template>
    <Head title="マイ食品" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex flex-col gap-4">
                    <Link
                        href="/meals"
                        class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink-muted transition-colors hover:text-primary"
                    >
                        <ArrowLeft :size="16" :stroke-width="1.6" />
                        食事記録
                    </Link>
                    <PageTitleOrnament
                        title="マイ食品"
                        subtitle="よく食べる食品を登録して、記録を素早く追加できます。"
                        align="left"
                    />
                </div>
            </PageSectionCard>

            <PageSectionCard padding="sm">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-0 flex-1">
                        <Label class="font-sans text-xs">検索</Label>
                        <Input
                            v-model="search"
                            type="text"
                            placeholder="食品名"
                            class="mt-1"
                            @keydown.enter="applySearch"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="font-sans"
                        @click="applySearch"
                    >
                        検索
                    </Button>
                    <Button type="button" class="font-sans" @click="openCreate">
                        <Plus :size="14" :stroke-width="1.6" />
                        追加
                    </Button>
                </div>
                <p
                    v-if="message"
                    class="mt-3 font-sans text-sm"
                    :class="
                        message.includes('失敗')
                            ? 'text-destructive'
                            : 'text-cd-moss'
                    "
                >
                    {{ message }}
                </p>
            </PageSectionCard>

            <PageSectionCard padding="none" aria-label="マイ食品一覧">
                <ul v-if="foods.length > 0" class="flex flex-col">
                    <li
                        v-for="food in foods"
                        :key="food.id"
                        class="flex items-start justify-between gap-3 border-b border-cd-line px-5 py-4 last:border-b-0"
                    >
                        <div class="min-w-0">
                            <p class="font-sans text-base font-semibold text-cd-ink">
                                {{ food.name }}
                            </p>
                            <p class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                                {{ food.serving_label }} ·
                                {{ formatNum(food.kcal) }} kcal · P
                                {{ formatNum(food.protein_g) }} / F
                                {{ formatNum(food.fat_g) }} / C
                                {{ formatNum(food.carb_g) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :aria-label="`${food.name} を編集`"
                                @click="openEdit(food)"
                            >
                                <Pencil :size="14" :stroke-width="1.6" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :aria-label="`${food.name} を削除`"
                                @click="deleteFood(food)"
                            >
                                <Trash2 :size="14" :stroke-width="1.6" />
                            </Button>
                        </div>
                    </li>
                </ul>
                <p v-else class="px-5 py-6 font-sans text-sm text-cd-ink-muted">
                    マイ食品はまだありません。空の状態から始められます。
                </p>
            </PageSectionCard>
        </div>
    </div>

    <Dialog :open="showModal" @update:open="(v) => (showModal = v)">
        <DialogContent class="bg-cd-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="font-sans">
                    {{ editing ? 'マイ食品を編集' : 'マイ食品を追加' }}
                </DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    1 サービングあたりの栄養値を登録します。
                </DialogDescription>
            </DialogHeader>
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2 flex flex-col gap-1">
                    <Label class="font-sans text-xs">名前</Label>
                    <Input v-model="form.name" type="text" maxlength="100" />
                </div>
                <div class="col-span-2 flex flex-col gap-1">
                    <Label class="font-sans text-xs">サービング表示</Label>
                    <Input
                        v-model="form.serving_label"
                        type="text"
                        maxlength="50"
                        placeholder="1杯 / 1個 など"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">kcal</Label>
                    <Input v-model="form.kcal" type="number" min="0" step="0.1" />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">P (g)</Label>
                    <Input
                        v-model="form.protein_g"
                        type="number"
                        min="0"
                        step="0.1"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">F (g)</Label>
                    <Input v-model="form.fat_g" type="number" min="0" step="0.1" />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">C (g)</Label>
                    <Input
                        v-model="form.carb_g"
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
                    @click="showModal = false"
                >
                    キャンセル
                </Button>
                <Button
                    type="button"
                    class="font-sans"
                    :disabled="saving"
                    @click="saveFood"
                >
                    保存
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
