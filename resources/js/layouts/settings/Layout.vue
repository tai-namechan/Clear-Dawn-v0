<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ShieldCheck, SlidersHorizontal, User } from '@lucide/vue';
import type { Component } from 'vue';
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { index as lifeAreasIndex } from '@/routes/life-areas';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

interface SettingsNavItem {
    title: string;
    description: string;
    icon: Component;
    href: NavItem['href'];
}

const sidebarNavItems: SettingsNavItem[] = [
    {
        title: 'プロフィール',
        description: '名前・メールアドレスなど',
        icon: User,
        href: editProfile(),
    },
    {
        title: 'セキュリティ',
        description: 'パスワードやログイン設定',
        icon: ShieldCheck,
        href: editSecurity(),
    },
    {
        title: '領域管理',
        description: '領域の作成・編集・削除',
        icon: SlidersHorizontal,
        href: lifeAreasIndex(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6 md:px-6 md:pb-8">
        <div class="mx-auto w-full max-w-5xl">
            <Heading
                title="設定"
                description="アカウントなどの各種設定を管理します。"
            />

            <div
                class="mt-8 grid grid-cols-1 items-start gap-8 lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-10 xl:gap-12"
            >
                <aside class="w-full lg:w-auto">
                    <nav
                        aria-label="設定メニュー"
                        class="cd-shadow-soft flex flex-col gap-1 rounded-2xl border border-cd-line bg-cd-surface p-2"
                    >
                        <Link
                            v-for="item in sidebarNavItems"
                            :key="toUrl(item.href)"
                            :href="item.href"
                            class="flex items-start gap-3 rounded-xl border border-transparent px-3 py-3 transition-colors"
                            :class="
                                isCurrentOrParentUrl(item.href)
                                    ? 'border-cd-lavender-mist/40 bg-cd-lavender-mist/30 text-cd-dawn-deep'
                                    : 'text-cd-ink hover:bg-muted/50'
                            "
                        >
                            <component
                                :is="item.icon"
                                :size="18"
                                :stroke-width="1.6"
                                aria-hidden="true"
                                class="mt-0.5 shrink-0"
                                :class="
                                    isCurrentOrParentUrl(item.href)
                                        ? 'text-cd-dawn-deep'
                                        : 'text-cd-ink-muted'
                                "
                            />
                            <span class="flex min-w-0 flex-col gap-0.5">
                                <span
                                    class="font-serif text-sm tracking-[0.06em]"
                                >
                                    {{ item.title }}
                                </span>
                                <span
                                    class="font-sans text-xs"
                                    :class="
                                        isCurrentOrParentUrl(item.href)
                                            ? 'text-cd-dawn-deep/70'
                                            : 'text-cd-ink-muted'
                                    "
                                >
                                    {{ item.description }}
                                </span>
                            </span>
                        </Link>
                    </nav>
                </aside>

                <Separator class="lg:hidden" />

                <div class="min-w-0">
                    <section class="max-w-2xl space-y-12">
                        <slot />
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
