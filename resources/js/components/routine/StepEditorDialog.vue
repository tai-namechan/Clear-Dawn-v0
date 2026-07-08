<script setup lang="ts">
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
import type { RoutineItem } from '@/types/routine';

export type StepEditorPayload = {
    routine_item_id: string;
    target_blocks: number | null;
    target_amount: number | null;
    rest_seconds: number | null;
};

interface Props {
    open: boolean;
    routineItems: RoutineItem[];
    saving?: boolean;
}

withDefaults(defineProps<Props>(), {
    saving: false,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [payload: StepEditorPayload];
}>();

const selectedId = defineModel<string>('selectedRoutineItemId', {
    default: '',
});
const blocks = defineModel<string>('blocks', { default: '3' });
const amount = defineModel<string>('amount', { default: '' });
const restSeconds = defineModel<string>('restSeconds', { default: '60' });

function close(): void {
    emit('update:open', false);
}

function submit(): void {
    if (!selectedId.value) {
        return;
    }

    emit('submit', {
        routine_item_id: selectedId.value,
        target_blocks: blocks.value ? Number(blocks.value) : null,
        target_amount: amount.value ? Number(amount.value) : null,
        rest_seconds: restSeconds.value ? Number(restSeconds.value) : null,
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="bg-cd-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    class="font-serif text-lg tracking-[0.12em] text-cd-ink"
                >
                    ステップを追加
                </DialogTitle>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <Select v-model="selectedId" :disabled="saving">
                    <SelectTrigger>
                        <SelectValue placeholder="実施項目を選択" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="item in routineItems"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <div class="grid grid-cols-3 gap-2">
                    <Input
                        v-model="blocks"
                        type="number"
                        min="1"
                        placeholder="ブロック"
                        :disabled="saving"
                    />
                    <Input
                        v-model="amount"
                        type="number"
                        min="1"
                        placeholder="量"
                        :disabled="saving"
                    />
                    <Input
                        v-model="restSeconds"
                        type="number"
                        min="0"
                        placeholder="休憩(秒)"
                        :disabled="saving"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" :disabled="saving" @click="close">
                    キャンセル
                </Button>
                <Button
                    type="button"
                    :disabled="saving || !selectedId"
                    @click="submit"
                >
                    追加
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
