<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import RoutinesHubTabs from '@/components/routine/RoutinesHubTabs.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { apiFetch } from '@/lib/apiFetch';
import {
    routineItemCategoryLabels,
    trackingTypeLabels,
} from '@/lib/routineConstants';
import type {
    RoutineItem,
    RoutineItemCategory,
    TrackingType,
} from '@/types/routine';

interface Props {
    routineItems: RoutineItem[];
}

const props = defineProps<Props>();

const showModal = ref(false);
const editingItem = ref<RoutineItem | null>(null);
const formName = ref('');
const formCategory = ref<RoutineItemCategory>('strength');
const formTrackingType = ref<TrackingType>('weight_reps');
const formNote = ref('');
const saving = ref(false);
const formError = ref<string | null>(null);

const groupedItems = computed(() => {
    const groups = new Map<RoutineItemCategory, RoutineItem[]>();

    for (const item of props.routineItems) {
        const list = groups.get(item.category) ?? [];
        list.push(item);
        groups.set(item.category, list);
    }

    return (Object.keys(routineItemCategoryLabels) as RoutineItemCategory[])
        .filter((category) => groups.has(category))
        .map((category) => ({
            category,
            label: routineItemCategoryLabels[category],
            items: groups.get(category) ?? [],
        }));
});

function openCreate(): void {
    editingItem.value = null;
    formName.value = '';
    formCategory.value = 'strength';
    formTrackingType.value = 'weight_reps';
    formNote.value = '';
    formError.value = null;
    showModal.value = true;
}

function openEdit(item: RoutineItem): void {
    editingItem.value = item;
    formName.value = item.name;
    formCategory.value = item.category;
    formTrackingType.value = item.tracking_type;
    formNote.value = item.note ?? '';
    formError.value = null;
    showModal.value = true;
}

async function saveItem(): Promise<void> {
    if (!formName.value.trim()) {
        return;
    }

    saving.value = true;
    formError.value = null;

    const payload = {
        name: formName.value.trim(),
        category: formCategory.value,
        tracking_type: formTrackingType.value,
        note: formNote.value.trim() || null,
    };

    try {
        if (editingItem.value) {
            await apiFetch(`/routine-items/${editingItem.value.id}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
        } else {
            await apiFetch('/routine-items', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        }

        showModal.value = false;
        router.reload({ only: ['routineItems'] });
    } catch {
        formError.value = '保存に失敗しました。';
    } finally {
        saving.value = false;
    }
}

async function deleteItem(item: RoutineItem): Promise<void> {
    if (!confirm(`「${item.name}」を削除しますか？`)) {
        return;
    }

    await apiFetch(`/routine-items/${item.id}`, { method: 'DELETE' });
    router.reload({ only: ['routineItems'] });
}
</script>

<template>
    <Head title="実施項目" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <PageTitleOrnament
                    title="部品ライブラリ"
                    subtitle="メニュー編集の中で作るのが基本です。ここは整理用です。"
                    align="left"
                />

                <Button
                    type="button"
                    class="mt-2 shrink-0"
                    @click="openCreate"
                >
                    <Plus :size="16" :stroke-width="1.8" />
                    追加
                </Button>
            </div>

            <RoutinesHubTabs />

            <section
                v-for="group in groupedItems"
                :key="group.category"
                :aria-label="group.label"
                class="cd-panel"
            >
                <h2
                    class="border-b border-cd-line px-5 py-4 font-sans text-base font-semibold text-cd-ink"
                >
                    {{ group.label }}
                </h2>
                <ul class="flex flex-col">
                    <li
                        v-for="item in group.items"
                        :key="item.id"
                        class="flex items-center justify-between gap-3 border-b border-cd-line/60 px-5 py-4 last:border-b-0"
                        :class="{ 'opacity-55': !item.is_active }"
                    >
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="`/routine-items/${item.id}`"
                                class="group flex items-center gap-1"
                            >
                                <p
                                    class="truncate font-sans text-base font-semibold text-cd-ink group-hover:text-primary"
                                >
                                    {{ item.name }}
                                </p>
                                <ChevronRight
                                    :size="16"
                                    :stroke-width="1.6"
                                    class="shrink-0 text-cd-ink-muted opacity-0 transition-opacity group-hover:opacity-100"
                                />
                            </Link>
                            <p
                                class="mt-0.5 font-sans text-xs text-cd-ink-muted"
                            >
                                {{ trackingTypeLabels[item.tracking_type] }}
                                <span
                                    v-if="item.videos_count"
                                    class="before:mx-1.5 before:content-['·']"
                                >
                                    動画 {{ item.videos_count }}
                                </span>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`${item.name} を編集`"
                                @click="openEdit(item)"
                            >
                                <Pencil :size="15" :stroke-width="1.6" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`${item.name} を削除`"
                                @click="deleteItem(item)"
                            >
                                <Trash2 :size="15" :stroke-width="1.6" />
                            </Button>
                        </div>
                    </li>
                </ul>
            </section>

            <div
                v-if="routineItems.length === 0"
                class="py-12 text-center font-sans text-sm text-cd-ink-muted"
            >
                <p>実施項目がまだありません。</p>
                <p class="mt-2">
                    上の「追加」ボタンから、ルーティンで使う項目を登録しましょう。
                </p>
            </div>
        </div>
    </div>

    <Dialog :open="showModal" @update:open="(v) => (showModal = v)">
        <DialogContent class="bg-cd-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    class="font-serif text-lg tracking-[0.12em] text-cd-ink"
                >
                    {{ editingItem ? '実施項目を編集' : '実施項目を追加' }}
                </DialogTitle>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <Input
                    v-model="formName"
                    placeholder="項目名"
                    maxlength="100"
                    :disabled="saving"
                />

                <Select v-model="formCategory" :disabled="saving">
                    <SelectTrigger>
                        <SelectValue placeholder="カテゴリ" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="(label, value) in routineItemCategoryLabels"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="formTrackingType" :disabled="saving">
                    <SelectTrigger>
                        <SelectValue placeholder="記録形式" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="(label, value) in trackingTypeLabels"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Input
                    v-model="formNote"
                    placeholder="メモ（任意）"
                    :disabled="saving"
                />

                <p v-if="formError" class="font-sans text-xs text-destructive">
                    {{ formError }}
                </p>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="saving"
                    @click="showModal = false"
                >
                    キャンセル
                </Button>
                <Button
                    type="button"
                    :disabled="saving || !formName.trim()"
                    @click="saveItem"
                >
                    保存
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
