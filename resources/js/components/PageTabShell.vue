<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, useSlots } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';

interface Props {
    title: string;
    subtitle?: string;
    /** 指定時はタイトル上に戻るリンクを出す */
    backHref?: string;
    backLabel?: string;
    ariaLabel?: string;
}

withDefaults(defineProps<Props>(), {
    subtitle: undefined,
    backHref: undefined,
    backLabel: '戻る',
    ariaLabel: undefined,
});

const slots = useSlots();
const hasHeaderRight = computed(
    () => Boolean(slots.calendar) || Boolean(slots.actions),
);
const hasBody = computed(() => Boolean(slots.default));
</script>

<template>
    <PageSectionCard :aria-label="ariaLabel">
        <div class="flex flex-col gap-3">
            <Link
                v-if="backHref"
                :href="backHref"
                class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink-muted transition-colors hover:text-primary"
            >
                ← {{ backLabel }}
            </Link>

            <div
                class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-start md:justify-between"
            >
                <div class="min-w-0 flex-1">
                    <PageTitleOrnament
                        :title="title"
                        :subtitle="subtitle"
                        align="left"
                    />
                    <slot name="badge" />
                </div>

                <div
                    v-if="hasHeaderRight"
                    class="flex flex-col gap-2 md:flex-row md:items-center md:justify-end md:pt-1"
                >
                    <div
                        v-if="$slots.calendar"
                        class="flex justify-center md:justify-end"
                    >
                        <slot name="calendar" />
                    </div>
                    <div
                        v-if="$slots.actions"
                        class="flex flex-wrap items-center justify-end gap-2"
                    >
                        <slot name="actions" />
                    </div>
                </div>
            </div>

            <div v-if="$slots.tabs" class="mt-2">
                <slot name="tabs" />
            </div>
        </div>

        <div v-if="hasBody" class="mt-5 border-t border-cd-line pt-5">
            <slot />
        </div>
    </PageSectionCard>
</template>
