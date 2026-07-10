<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import ProductSwitcher from '@/components/os/ProductSwitcher.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { index as lifeAreasIndex } from '@/routes/life-areas';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { isCurrentUrl } = useCurrentUrl();
const onDashboard = computed(() => isCurrentUrl(dashboard()));

const now = new Date();
const today = [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, '0'),
    String(now.getDate()).padStart(2, '0'),
].join('/');
const todayIso = [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, '0'),
    String(now.getDate()).padStart(2, '0'),
].join('-');
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2 md:gap-3">
            <SidebarTrigger class="-ml-1 shrink-0" />
            <h1
                class="truncate font-serif text-lg tracking-[0.12em] text-cd-dawn-deep md:text-xl"
            >
                Clear Dawn
            </h1>
            <ProductSwitcher />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex shrink-0 items-center gap-2 md:gap-3">
            <div
                v-if="onDashboard"
                class="cd-frost hidden items-center gap-3 rounded-full border border-cd-line px-4 py-2 shadow-sm sm:flex md:gap-4 md:px-5"
            >
                <time
                    :datetime="todayIso"
                    class="cursor-default font-serif text-base tracking-[0.12em] text-cd-ink lining-nums select-none"
                >
                    {{ today }}
                </time>

                <div aria-hidden="true" class="cd-header-divider h-5" />

                <Link
                    :href="lifeAreasIndex()"
                    aria-label="領域管理"
                    class="group inline-flex items-center gap-1.5 font-serif text-base tracking-[0.12em] text-cd-ink transition-colors hover:text-cd-dawn-deep"
                >
                    <SlidersHorizontal
                        :size="16"
                        :stroke-width="1.6"
                        class="opacity-80 transition-opacity group-hover:opacity-100"
                        aria-hidden="true"
                    />
                    <span class="underline-offset-4 group-hover:underline">
                        領域管理
                    </span>
                </Link>
            </div>

            <HeaderUserMenu compact />
        </div>
    </header>
</template>
