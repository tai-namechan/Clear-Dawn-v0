<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
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
import { destroy, store, update } from '@/routes/matrix-cell-items';
import type { MatrixCell } from '@/types/matrix';

interface Props {
    open: boolean;
    cell: MatrixCell | null;
    areaName: string;
    rowLabel: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const editingItemId = ref<string | null>(null);

watch(
    () => props.open,
    () => {
        editingItemId.value = null;
    },
);

function deleteItem(itemId: string): void {
    router.delete(destroy.url(itemId), { preserveScroll: true });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle class="font-serif text-xl tracking-[0.12em]">
                    {{ areaName }}
                </DialogTitle>
                <DialogDescription class="font-serif tracking-[0.08em]">
                    {{ rowLabel }}
                </DialogDescription>
            </DialogHeader>

            <ul v-if="cell && cell.items.length > 0" class="flex flex-col">
                <li
                    v-for="item in cell.items"
                    :key="item.id"
                    class="border-b border-cd-line/60 py-3 last:border-b-0"
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

                    <div v-else class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="text-[15px] leading-relaxed text-cd-ink"
                                :class="{
                                    'text-cd-ink-muted line-through':
                                        item.is_completed,
                                }"
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
                        <div class="flex shrink-0 gap-1">
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
                        </div>
                    </div>
                </li>
            </ul>

            <p v-else class="py-2 text-sm text-cd-ink-muted">
                まだ項目がありません。
            </p>

            <Form
                v-if="cell?.id"
                v-bind="store.form(cell.id)"
                :options="{ preserveScroll: true }"
                reset-on-success
                class="flex flex-col gap-2 border-t border-cd-line/60 pt-4"
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
                <div class="flex justify-end">
                    <Button type="submit" size="sm" :disabled="processing">
                        <Plus :size="15" :stroke-width="1.8" />
                        追加
                    </Button>
                </div>
            </Form>
        </DialogContent>
    </Dialog>
</template>
