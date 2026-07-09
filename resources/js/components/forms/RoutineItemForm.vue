<script setup lang="ts">
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    routineItemCategoryLabels,
    trackingTypeLabels,
} from '@/lib/routineConstants';
import type { RoutineItemCategory, TrackingType } from '@/types/routine';

interface Props {
    disabled?: boolean;
    error?: string | null;
}

withDefaults(defineProps<Props>(), {
    disabled: false,
    error: null,
});

const name = defineModel<string>('name', { required: true });
const category = defineModel<RoutineItemCategory>('category', {
    required: true,
});
const trackingType = defineModel<TrackingType>('trackingType', {
    required: true,
});
const note = defineModel<string>('note', { required: true });
</script>

<template>
    <div class="flex flex-col gap-3">
        <Input
            v-model="name"
            placeholder="項目名"
            maxlength="100"
            :disabled="disabled"
        />

        <Select v-model="category" :disabled="disabled">
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

        <Select v-model="trackingType" :disabled="disabled">
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
            v-model="note"
            placeholder="メモ（任意）"
            :disabled="disabled"
        />

        <p v-if="error" class="font-sans text-xs text-destructive">
            {{ error }}
        </p>
    </div>
</template>
