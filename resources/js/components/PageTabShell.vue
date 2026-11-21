<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, useSlots } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';

interface Props {
    title: string;
    subtitle?: string;
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
const hasBodyCard = computed(() => Boolean(slots.tabs) || hasBody.value);
</script>

<template>
    <section :aria-label="ariaLabel" class="cd-page-tab-shell">
        <!-- On the dawn sky: title, date and page actions -->
        <div class="cd-hero-stage">
            <div class="flex flex-col gap-3">
                <Link
                    v-if="backHref"
                    :href="backHref"
                    class="cd-header-hero-link inline-flex items-center gap-2 self-start font-sans text-sm font-medium transition-colors hover:opacity-80"
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
            </div>
        </div>
        <!-- White summary card overlapping the bottom of the sky -->
        <div v-if="$slots.hero" class="cd-panel cd-hero-summary">
            <slot name="hero" />
        </div>
        <PageSectionCard
            v-if="hasBodyCard"
            padding="none"
            class="cd-page-tab-body"
        >
            <div v-if="$slots.tabs" class="cd-page-tab-tabs">
                <slot name="tabs" />
            </div>
            <div v-if="hasBody" class="cd-page-tab-content"><slot /></div>
        </PageSectionCard>
    </section>
</template>
