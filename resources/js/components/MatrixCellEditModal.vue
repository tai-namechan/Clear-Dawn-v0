<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Check, GripVertical, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { destroy, store, toggle, update } from '@/routes/matrix-cell-items';
import type { MatrixCell, MatrixCellItem } from '@/types/matrix';

interface Props {
    open: boolean;
    cell: MatrixCell | null;
    areaName: string;
    rowLabel: string;
    description: string;
    isCheckable: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const editingItemId = ref<string | null>(null);
const showAddForm = ref(false);

watch(
    () => props.open,
    () => {
        editingItemId.value = null;
        showAddForm.value = false;
    },
);

function toggleCompletion(item: MatrixCellItem): void {
    router.patch(toggle.url(item.id), {}, { preserveScroll: true });
}

function deleteItem(itemId: string): void {
    router.delete(destroy.url(itemId), { preserveScroll: true });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-h-[85vh] overflow-y-auto bg-cd-surface sm:max-w-xl"
        >
            <DialogHeader class="items-center gap-2 text-center sm:text-center">
                <DialogTitle
                    class="font-serif text-xl font-normal tracking-[0.16em] text-primary"
                >
                    {{ areaName }} / {{ rowLabel }}
                </DialogTitle>
                <div
                    aria-hidden="true"
                    class="cd-mask-ornament h-4 w-32 text-cd-gilt"
                />
                <DialogDescription
                    class="max-w-sm font-sans text-sm leading-relaxed whitespace-pre-line text-cd-ink-muted"
                >
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <ul
                v-if="cell && cell.items.length > 0"
                class="flex flex-col gap-2"
            >
                <li
                    v-for="item in cell.items"
                    :key="item.id"
                    class="rounded-lg border border-cd-line/70 bg-white/70 px-3 py-3"
                >
                    <Form
                        v-if="editingItemId === item.id"
                        v-bind="update.form(item.id)"
                        :options="{ preserveScroll: true }"
                        class="flex flex-col gap-2"
                        v-slot="{ errors, processing }"
                        @success="editingItemId = null"
                    >
                        <Input
                            name="title"
                            :default-value="item.title"
                            required
                            maxlength="255"
                            placeholder="項目名"
                        />
                        <InputError :message="errors.title" />
                        <textarea
                            name="memo"
                            :value="item.memo ?? ''"
                            rows="2"
                            maxlength="2000"
                            placeholder="補足メモ（任意）"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        ></textarea>
                        <InputError :message="errors.memo" />
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="editingItemId = null"
                            >
                                キャンセル
                            </Button>
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="processing"
                            >
                                保存
                            </Button>
                        </div>
                    </Form>

                    <div v-else class="flex items-start gap-2">
                        <span
                            aria-hidden="true"
                            class="mt-0.5 shrink-0 cursor-grab text-cd-ink-muted/40"
                        >
                            <GripVertical :size="16" :stroke-width="1.6" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[17px] leading-relaxed md:text-lg"
                                :class="
                                    item.is_completed
                                        ? 'font-matrix font-matrix--done line-through'
                                        : 'font-matrix'
                                "
                            >
                                {{ item.title }}
                            </p>
                            <p
                                v-if="item.memo"
                                class="mt-0.5 text-sm whitespace-pre-line text-cd-ink-muted"
                            >
                                {{ item.memo }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`${item.title} を編集`"
                                @click="editingItemId = item.id"
                            >
                                <Pencil :size="15" :stroke-width="1.6" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:text-destructive"
                                :aria-label="`${item.title} を削除`"
                                @click="deleteItem(item.id)"
                            >
                                <Trash2 :size="15" :stroke-width="1.6" />
                            </Button>
                            <button
                                v-if="isCheckable"
                                type="button"
                                role="checkbox"
                                :aria-checked="item.is_completed"
                                :aria-label="`${item.title} を${item.is_completed ? '再開' : '完了'}にする`"
                                class="ml-2 inline-flex size-5 shrink-0 items-center justify-center rounded-[3px] border transition-colors"
                                :class="
                                    item.is_completed
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-cd-ink-muted/60 bg-white'
                                "
                                @click="toggleCompletion(item)"
                            >
                                <Check
                                    v-if="item.is_completed"
                                    :size="13"
                                    :stroke-width="2.4"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </div>
                </li>
            </ul>

            <p v-else class="py-1 text-center text-sm text-cd-ink-muted">
                まだ項目がありません。
            </p>

            <template v-if="cell?.id">
                <button
                    v-if="!showAddForm"
                    type="button"
                    class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-cd-ink-muted/40 py-2.5 font-sans text-sm text-cd-ink-muted transition-colors hover:border-cd-ink-muted/70 hover:bg-muted/40 hover:text-cd-ink"
                    @click="showAddForm = true"
                >
                    <Plus :size="15" :stroke-width="1.8" aria-hidden="true" />
                    項目を追加
                </button>

                <Form
                    v-else
                    v-bind="store.form(cell.id)"
                    :options="{ preserveScroll: true }"
                    reset-on-success
                    class="flex flex-col gap-2 rounded-md border border-cd-line/70 bg-white/60 p-3"
                    v-slot="{ errors, processing }"
                >
                    <Input
                        name="title"
                        required
                        maxlength="255"
                        placeholder="新しい項目"
                    />
                    <InputError :message="errors.title" />
                    <textarea
                        name="memo"
                        rows="2"
                        maxlength="2000"
                        placeholder="補足メモ（任意）"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    ></textarea>
                    <InputError :message="errors.memo" />
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="showAddForm = false"
                        >
                            キャンセル
                        </Button>
                        <Button type="submit" size="sm" :disabled="processing">
                            <Plus :size="15" :stroke-width="1.8" />
                            追加
                        </Button>
                    </div>
                </Form>
            </template>

            <div
                class="sticky bottom-0 -mx-6 -mb-6 flex justify-end gap-3 border-t border-cd-line/70 bg-cd-surface/95 px-6 py-4 backdrop-blur-sm"
            >
                <Button
                    type="button"
                    class="min-w-32 font-sans tracking-[0.08em]"
                    @click="emit('update:open', false)"
                >
                    閉じる
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
