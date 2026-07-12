<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Brain,
    Link2,
    ListTodo,
    MessageSquare,
    Settings,
    Sun,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { home as kiokuHome, settings as kiokuSettings, sources as kiokuSources } from '@/routes/kioku';
import { home as yoyuHome } from '@/routes/yoyu';
import type { ProductKey } from '@/types/product';

type OsNavItem = {
    title: string;
    icon: Component;
    href: string;
    active: boolean;
};

const page = usePage();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const currentProduct = computed(
    () => page.props.currentProduct as ProductKey,
);

const yoyuTab = computed(() => {
    const query = page.url.includes('?')
        ? page.url.slice(page.url.indexOf('?') + 1)
        : '';
    const tab = new URLSearchParams(query).get('tab');

    return tab && tab.length > 0 ? tab : 'today';
});

const sidebarImage = computed(() => {
    if (currentProduct.value === 'kioku') {
        return '/images/products/sidebars/kioku-sidebar.jpg';
    }

    return '/images/products/sidebars/yoyu-sidebar.jpg';
});

const sidebarClass = computed(() =>
    currentProduct.value === 'kioku' ? 'os-sidebar os-sidebar-kioku' : 'os-sidebar os-sidebar-yoyu',
);

const navItems = computed((): OsNavItem[] => {
    if (currentProduct.value === 'kioku') {
        return [
            {
                title: '記憶',
                icon: Brain,
                href: kiokuHome.url(),
                active:
                    isCurrentUrl(kiokuHome.url()) ||
                    isCurrentOrParentUrl('/kioku/memories'),
            },
            {
                title: '取り込み元',
                icon: Link2,
                href: kiokuSources.url(),
                active: isCurrentUrl(kiokuSources.url()),
            },
            {
                title: '設定',
                icon: Settings,
                href: kiokuSettings.url(),
                active: isCurrentUrl(kiokuSettings.url()),
            },
        ];
    }

    const tabs: Array<{ key: string; title: string; icon: Component }> = [
        { key: 'today', title: '今日', icon: Sun },
        { key: 'tasks', title: 'タスク', icon: ListTodo },
        { key: 'mind', title: '頭の中', icon: Brain },
        { key: 'chat', title: '秘書', icon: MessageSquare },
    ];

    return tabs.map((tab) => ({
        title: tab.title,
        icon: tab.icon,
        href: yoyuHome.url({ query: { tab: tab.key } }),
        active: yoyuTab.value === tab.key,
    }));
});

const accentClass = computed(() =>
    currentProduct.value === 'kioku'
        ? 'border-os-kioku/20 bg-os-kioku text-white group-data-[collapsible=icon]:border-white/25 group-data-[collapsible=icon]:bg-white/20'
        : 'border-os-yoyu/20 bg-os-yoyu text-white group-data-[collapsible=icon]:border-white/25 group-data-[collapsible=icon]:bg-white/20',
);

const idleClass = computed(() =>
    currentProduct.value === 'kioku'
        ? 'border-transparent text-os-ink/80 hover:bg-os-kioku/10 hover:text-os-ink group-data-[collapsible=icon]:text-white/90 group-data-[collapsible=icon]:hover:bg-white/10 group-data-[collapsible=icon]:hover:text-white'
        : 'border-transparent text-os-ink/80 hover:bg-os-yoyu/10 hover:text-os-ink group-data-[collapsible=icon]:text-white/90 group-data-[collapsible=icon]:hover:bg-white/10 group-data-[collapsible=icon]:hover:text-white',
);
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        :class="sidebarClass"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 overflow-hidden"
        >
            <img
                :src="sidebarImage"
                alt=""
                class="h-full w-full object-cover object-top group-data-[collapsible=icon]:hidden"
            />
        </div>

        <!-- Branding + tagline live in the sidebar image; reserve space so nav clears them. -->
        <SidebarHeader class="relative z-10 min-h-48 group-data-[collapsible=icon]:min-h-8" />

        <SidebarContent class="relative z-10 overflow-visible">
            <nav
                aria-label="プロダクトメニュー"
                class="mt-20 flex flex-col items-center gap-3 group-data-[collapsible=icon]:mt-4 group-data-[collapsible=icon]:gap-4"
            >
                <Link
                    v-for="item in navItems"
                    :key="item.title"
                    :href="item.href"
                    :aria-current="item.active ? 'page' : undefined"
                    class="flex w-24 flex-col items-center justify-center gap-2 rounded-2xl border px-3 py-3 text-center transition-colors group-data-[collapsible=icon]:w-auto group-data-[collapsible=icon]:border-transparent group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:p-2"
                    :class="item.active ? accentClass : idleClass"
                    :preserve-state="currentProduct === 'yoyu'"
                    :replace="currentProduct === 'yoyu'"
                >
                    <component
                        :is="item.icon"
                        :size="22"
                        :stroke-width="1.5"
                    />
                    <span
                        class="text-xs font-semibold tracking-wide whitespace-nowrap group-data-[collapsible=icon]:hidden"
                    >
                        {{ item.title }}
                    </span>
                </Link>
            </nav>
        </SidebarContent>

        <SidebarFooter class="relative z-10 min-h-24" aria-hidden="true" />
    </Sidebar>
    <slot />
</template>
