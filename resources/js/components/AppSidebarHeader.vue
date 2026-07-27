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
        class="cd-app-header sticky top-0 z-30 flex min-h-16 shrink-0 items-center justify-between gap-2 px-4 py-3 transition-[width,height] ease-linear md:static md:z-auto md:px-6 md:py-4 landscape-compact:min-h-12 landscape-compact:py-2 landscape-compact:md:py-2"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2 md:gap-4">
            <SidebarTrigger
                class="shrink-0 text-cd-ink-muted hover:bg-muted/40 hover:text-[var(--cd-header-text)] max-md:hover:bg-white/10"
            />
            <h1
                class="cd-app-header-title hidden truncate font-serif text-[2rem] leading-none font-normal tracking-[0.16em] md:block md:text-[2.5rem] landscape-compact:md:text-[1.65rem] landscape-compact:md:tracking-[0.12em]"
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
                class="cd-header-control hidden items-center gap-3 rounded-full px-4 py-2 sm:flex md:gap-4 md:px-5 landscape-compact:gap-2 landscape-compact:px-3 landscape-compact:py-1"
            >
                <time
                    :datetime="todayIso"
                    class="cursor-default font-serif text-base tracking-[0.12em] text-[var(--cd-header-text)] lining-nums select-none landscape-compact:text-sm"
                >
                    {{ today }}
                </time>

                <div
                    aria-hidden="true"
                    class="cd-header-divider h-5 landscape-compact:h-4"
                />

                <Link
                    :href="lifeAreasIndex()"
                    aria-label="領域管理"
                    class="group inline-flex items-center gap-1.5 font-serif text-base tracking-[0.12em] text-[var(--cd-header-text)] transition-colors hover:text-[var(--cd-primary-strong)] max-md:hover:text-white landscape-compact:text-sm"
                >
                    <SlidersHorizontal
                        :size="16"
                        :stroke-width="1.6"
                        class="text-[var(--cd-header-text)] opacity-80 transition-opacity group-hover:opacity-100"
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
