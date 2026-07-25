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

const props = withDefaults(defineProps<Props>(), {
    subtitle: undefined,
    backHref: undefined,
    backLabel: '戻る',
    ariaLabel: undefined,
});

const slots = useSlots();
const hasAside = computed(() => Boolean(slots.aside));
const hasBody = computed(() => Boolean(slots.default));
</script>

<template>
    <div
        class="grid gap-4"
        :class="hasAside ? 'lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)]' : undefined"
    >
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
                    class="flex flex-wrap items-start justify-between gap-3"
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
                        v-if="$slots.actions"
                        class="flex shrink-0 flex-wrap items-center gap-2"
                    >
                        <slot name="actions" />
                    </div>
                </div>

                <div v-if="$slots.tabs" class="mt-2">
                    <slot name="tabs" />
                </div>
            </div>

            <div
                v-if="hasBody"
                class="mt-5 border-t border-cd-line pt-5"
            >
                <slot />
            </div>
        </PageSectionCard>

        <PageSectionCard
            v-if="hasAside"
            padding="sm"
            class="flex items-center justify-center"
        >
            <slot name="aside" />
        </PageSectionCard>
    </div>
</template>
