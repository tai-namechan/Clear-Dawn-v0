<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChartLine,
    CircleCheck,
    Clapperboard,
    Home,
    Settings,
} from '@lucide/vue';
import type { Component } from 'vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

interface CdNavItem {
    title: string;
    icon: Component;
    href: NavItem['href'];
    matchPrefix?: boolean;
}

const navItems: CdNavItem[] = [
    { title: 'ダッシュボード', icon: Home, href: dashboard() },
    {
        title: 'ルーティン',
        icon: CircleCheck,
        href: '/routines',
        matchPrefix: true,
    },
    {
        title: 'パフォーマンス管理',
        icon: ChartLine,
        href: '/records',
        matchPrefix: true,
    },
    { title: '動画', icon: Clapperboard, href: '/videos' },
    { title: '設定', icon: Settings, href: editProfile() },
];

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

function isNavActive(item: CdNavItem): boolean {
    if (item.matchPrefix) {
        return isCurrentOrParentUrl(item.href);
    }

    return isCurrentUrl(item.href);
}
</script>

<template>
    <Sidebar collapsible="icon" variant="floating" class="cd-sidebar">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 overflow-hidden group-data-[collapsible=icon]:hidden"
        >
            <img
                src="/images/decorations/stars-soft.png"
                alt=""
                class="absolute inset-x-0 top-0 w-full opacity-45"
            />
            <img
                src="/images/decorations/stars-soft.png"
                alt=""
                class="absolute inset-x-0 top-64 w-full rotate-180 opacity-25"
            />
            <img
                src="/images/decorations/moon-glow.png"
                alt=""
                class="absolute top-28 right-4 w-20 -scale-x-100 opacity-90"
            />
            <div
                class="cd-mask-violin absolute bottom-14 left-1/2 h-56 w-40 -translate-x-1/2 rotate-12 text-cd-gilt/80"
            />
        </div>

        <SidebarHeader class="relative z-10 items-center">
            <Link
                :href="dashboard()"
                aria-label="Clear Dawn ダッシュボード"
                class="mx-auto mt-5 flex items-baseline font-serif text-white group-data-[collapsible=icon]:mt-1"
            >
                <span
                    class="text-6xl leading-none group-data-[collapsible=icon]:text-2xl"
                    >C</span
                >
                <span
                    class="-ml-3 translate-y-3 text-5xl leading-none group-data-[collapsible=icon]:hidden"
                    >D</span
                >
            </Link>
            <span
                class="mt-1 font-serif text-[0.7rem] tracking-[0.32em] text-white/70 group-data-[collapsible=icon]:hidden"
            >
                Clear Dawn
            </span>
        </SidebarHeader>

        <SidebarContent class="relative z-10 overflow-visible">
            <nav
                aria-label="メインメニュー"
                class="mt-24 flex flex-col items-center gap-3 group-data-[collapsible=icon]:mt-8 group-data-[collapsible=icon]:gap-5"
            >
                <template v-for="item in navItems" :key="item.title">
                    <Link
                        :href="item.href"
                        :aria-current="isNavActive(item) ? 'page' : undefined"
                        class="flex w-24 flex-col items-center justify-center gap-2 rounded-2xl border px-3 py-3 transition-colors group-data-[collapsible=icon]:w-auto group-data-[collapsible=icon]:border-transparent group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:p-2"
                        :class="
                            isNavActive(item)
                                ? 'border-white/15 bg-white/10 text-white'
                                : 'border-transparent text-white/80 hover:bg-white/5 hover:text-white'
                        "
                    >
                        <component
                            :is="item.icon"
                            :size="26"
                            :stroke-width="1.4"
                        />
                        <span
                            class="font-serif text-xs tracking-[0.2em] whitespace-nowrap group-data-[collapsible=icon]:hidden"
                        >
                            {{ item.title }}
                        </span>
                    </Link>
                </template>
            </nav>
        </SidebarContent>

        <SidebarFooter class="relative z-10 min-h-20 pb-6" aria-hidden="true" />
    </Sidebar>
    <slot />
</template>
