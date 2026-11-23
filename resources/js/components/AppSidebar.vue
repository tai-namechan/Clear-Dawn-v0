<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { clearDawnNavIcons } from '@/lib/clearDawnNavIcons';
import {
    clearDawnNavItems as navItems,
    isNavPathActive,
} from '@/lib/clearDawnNavigation.mjs';
import { dashboard } from '@/routes';

const page = usePage();
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="cd-sidebar">
        <!--
            cd-sidebar-panel: mobile SheetContent is teleported outside .cd-sidebar,
            so app.css matches the drawer via :has(.cd-sidebar-panel).
        -->
        <div
            class="cd-sidebar-panel relative flex h-full min-h-0 w-full flex-1 flex-col"
        >
            <div
                aria-hidden="true"
                class="cd-sidebar-decor pointer-events-none absolute inset-0 overflow-hidden group-data-[collapsible=icon]:hidden"
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
                    class="absolute top-28 right-4 w-20 -scale-x-100 opacity-90 landscape-compact:top-16 landscape-compact:w-12"
                />
            </div>

            <SidebarHeader class="relative z-10 items-center">
                <Link
                    :href="dashboard()"
                    aria-label="Clear Dawn ダッシュボード"
                    class="cd-sidebar-brand mx-auto mt-8 flex items-baseline font-serif group-data-[collapsible=icon]:mt-1 landscape-compact:mt-4"
                >
                    <span
                        class="text-6xl leading-none group-data-[collapsible=icon]:text-2xl landscape-compact:text-4xl"
                        >C</span
                    >
                    <span
                        class="-ml-3 translate-y-3 text-5xl leading-none group-data-[collapsible=icon]:hidden landscape-compact:-ml-2 landscape-compact:translate-y-2 landscape-compact:text-3xl"
                        >D</span
                    >
                </Link>
                <span
                    class="cd-sidebar-wordmark mt-3 font-serif text-[0.7rem] tracking-[0.32em] group-data-[collapsible=icon]:hidden landscape-compact:mt-2 landscape-compact:tracking-[0.24em]"
                >
                    Clear Dawn
                </span>
            </SidebarHeader>

            <SidebarContent
                class="relative z-10 justify-center overflow-visible landscape-compact:justify-start landscape-compact:overflow-y-auto"
            >
                <nav
                    aria-label="メインメニュー"
                    class="mt-8 flex flex-col items-center gap-5 group-data-[collapsible=icon]:mt-8 group-data-[collapsible=icon]:gap-5 landscape-compact:mt-10 landscape-compact:gap-2"
                >
                    <template v-for="item in navItems" :key="item.key">
                        <Link
                            :href="item.href"
                            :aria-current="
                                isNavPathActive(item.paths, page.url)
                                    ? 'page'
                                    : undefined
                            "
                            class="cd-sidebar-nav-link flex w-24 flex-col items-center justify-center gap-2 rounded-2xl px-3 py-3 transition-colors group-data-[collapsible=icon]:w-auto group-data-[collapsible=icon]:border-transparent group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:p-2 landscape-compact:w-20 landscape-compact:gap-1 landscape-compact:rounded-xl landscape-compact:px-2 landscape-compact:py-1.5"
                            :class="{
                                'is-active': isNavPathActive(
                                    item.paths,
                                    page.url,
                                ),
                            }"
                        >
                            <component
                                :is="clearDawnNavIcons[item.key]"
                                :size="26"
                                :stroke-width="2.2"
                                class="landscape-compact:size-5"
                            />
                            <span
                                class="font-serif text-xs font-semibold tracking-[0.2em] whitespace-nowrap group-data-[collapsible=icon]:hidden landscape-compact:text-[0.65rem] landscape-compact:tracking-[0.12em]"
                            >
                                {{ item.label }}
                            </span>
                        </Link>
                    </template>
                </nav>
            </SidebarContent>

            <SidebarFooter
                class="relative z-10 min-h-20 pb-6 landscape-compact:min-h-8 landscape-compact:pb-2"
                aria-hidden="true"
            />
        </div>
    </Sidebar>
    <slot />
</template>
