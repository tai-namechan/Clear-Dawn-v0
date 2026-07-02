<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, FolderGit2, LayoutGrid } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="cd-sidebar">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 overflow-hidden group-data-[collapsible=icon]:hidden"
        >
            <img
                src="/images/decorations/stars-soft.png"
                alt=""
                class="absolute inset-x-0 top-0 w-full opacity-40"
            />
            <img
                src="/images/decorations/moon-glow.png"
                alt=""
                class="absolute top-16 right-3 w-16 opacity-80"
            />
            <!-- Darkens the lower gradient so footer text stays readable -->
            <div
                class="absolute inset-x-0 bottom-0 h-44 bg-gradient-to-t from-cd-dawn-soft/50 to-transparent"
            />
            <div
                class="cd-mask-violin absolute bottom-28 left-1/2 h-60 w-24 -translate-x-1/2 text-white/30"
            />
        </div>
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
