<script setup lang="ts">
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { LifeArea } from '@/types/matrix';

interface Props {
    lifeAreas: LifeArea[];
    disabled?: boolean;
    categoryLabel?: string;
    totalDurationLabel?: string;
    stepCountLabel?: string;
}

withDefaults(defineProps<Props>(), {
    disabled: false,
    categoryLabel: '—',
    totalDurationLabel: '—',
    stepCountLabel: '0 件',
});

const name = defineModel<string>('name', { required: true });
const description = defineModel<string>('description', { required: true });
const lifeAreaId = defineModel<string | null>('lifeAreaId', { required: true });
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-2 md:col-span-2">
            <label class="font-sans text-xs font-medium text-cd-ink-muted">
                ルーティン名
            </label>
            <Input
                v-model="name"
                maxlength="100"
                placeholder="例: 朝の基礎トレーニング"
                :disabled="disabled"
            />
        </div>

        <div class="space-y-2 md:col-span-2">
            <label class="font-sans text-xs font-medium text-cd-ink-muted">
                説明
            </label>
            <textarea
                v-model="description"
                rows="3"
                placeholder="任意"
                class="border-input bg-white ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 font-sans text-sm text-cd-ink focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="disabled"
            />
        </div>

        <div class="space-y-2">
            <label class="font-sans text-xs font-medium text-cd-ink-muted">
                カテゴリ
            </label>
            <p class="font-sans text-sm text-cd-ink">
                {{ categoryLabel }}
            </p>
        </div>

        <div class="space-y-2">
            <label class="font-sans text-xs font-medium text-cd-ink-muted">
                領域
            </label>
            <Select
                :model-value="lifeAreaId ?? 'none'"
                :disabled="disabled"
                @update:model-value="
                    (v) => (lifeAreaId = v && v !== 'none' ? String(v) : null)
                "
            >
                <SelectTrigger>
                    <SelectValue placeholder="未設定" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">
                        未設定
                    </SelectItem>
                    <SelectItem
                        v-for="area in lifeAreas"
                        :key="area.id"
                        :value="area.id"
                    >
                        {{ area.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="space-y-2">
            <label class="font-sans text-xs font-medium text-cd-ink-muted">
                合計時間
            </label>
            <p class="font-sans text-sm text-cd-ink">
                {{ totalDurationLabel }}
            </p>
        </div>

        <div class="space-y-2">
            <label class="font-sans text-xs font-medium text-cd-ink-muted">
                ステップ数
            </label>
            <p class="font-sans text-sm text-cd-ink">
                {{ stepCountLabel }}
            </p>
        </div>
    </div>
</template>
