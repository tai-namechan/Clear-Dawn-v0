<script setup lang="ts">
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Exercise } from '@/types/training';

interface Props {
    exercises: Exercise[];
    saving?: boolean;
}

defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });
const selectedExerciseId = defineModel<string>('selectedExerciseId', {
    required: true,
});
const sets = defineModel<string>('sets', { required: true });
const reps = defineModel<string>('reps', { required: true });
const rest = defineModel<string>('rest', { required: true });

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="bg-cd-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    class="font-serif text-lg tracking-[0.12em] text-cd-ink"
                >
                    ステップを追加
                </DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    種目と目標値を設定してステップを追加します。
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <Select v-model="selectedExerciseId" :disabled="saving">
                    <SelectTrigger>
                        <SelectValue placeholder="種目を選択" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="exercise in exercises"
                            :key="exercise.id"
                            :value="exercise.id"
                        >
                            {{ exercise.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <div class="grid grid-cols-3 gap-2">
                    <Input
                        v-model="sets"
                        type="number"
                        min="1"
                        placeholder="セット"
                        :disabled="saving"
                    />
                    <Input
                        v-model="reps"
                        type="number"
                        min="1"
                        placeholder="回数"
                        :disabled="saving"
                    />
                    <Input
                        v-model="rest"
                        type="number"
                        min="0"
                        placeholder="休憩(秒)"
                        :disabled="saving"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="saving"
                    @click="open = false"
                >
                    キャンセル
                </Button>
                <Button
                    type="button"
                    :disabled="saving || !selectedExerciseId"
                    @click="emit('submit')"
                >
                    追加
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
