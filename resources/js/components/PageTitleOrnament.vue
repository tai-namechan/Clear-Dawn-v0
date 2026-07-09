<script setup lang="ts">
interface Props {
    title: string;
    subtitle?: string;
    align?: 'center' | 'left';
    /** Dashboard 等、左寄せタイトルを一段読みやすくする */
    size?: 'default' | 'prominent';
    /**
     * brand: セリフ（ダッシュボード等の特別画面）
     * app: 標準ゴシック（登録・実行画面の可読性優先）
     */
    tone?: 'brand' | 'app';
}

withDefaults(defineProps<Props>(), {
    subtitle: undefined,
    align: 'center',
    size: 'default',
    tone: 'app',
});
</script>

<template>
    <div
        class="flex flex-col gap-2"
        :class="
            align === 'center'
                ? 'items-center py-4 text-center'
                : 'items-start py-1 text-left'
        "
    >
        <h1
            class="font-normal text-primary"
            :class="[
                tone === 'brand' ? 'font-serif' : 'font-sans font-semibold',
                align === 'center'
                    ? 'text-4xl tracking-[0.08em] md:text-5xl'
                    : size === 'prominent'
                      ? 'text-4xl leading-tight tracking-[0.04em] md:text-[2.625rem]'
                      : 'text-2xl leading-tight tracking-[0.02em] md:text-3xl',
            ]"
        >
            {{ title }}
        </h1>
        <p
            v-if="subtitle"
            class="font-sans text-sm text-cd-ink-muted"
            :class="tone === 'brand' ? 'tracking-[0.12em]' : 'tracking-normal'"
        >
            {{ subtitle }}
        </p>
        <div
            aria-hidden="true"
            class="max-w-full"
            :class="
                align === 'center'
                    ? 'cd-mask-ornament mt-1 h-8 w-72 text-cd-ink-muted/60'
                    : size === 'prominent'
                      ? 'cd-title-rule mt-1.5 w-44'
                      : 'cd-title-rule w-40'
            "
        />
    </div>
</template>
