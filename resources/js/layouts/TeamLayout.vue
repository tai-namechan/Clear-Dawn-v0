<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    Menu,
    Users,
    X,
    LogOut,
    ClipboardList,
    BarChart3,
    Settings,
} from '@lucide/vue';
import { computed, ref } from 'vue';

interface TeamProps {
    team?: { name: string; slug: string };
    actor?: { name: string; email: string };
}
const page = usePage();
const team = computed(() => (page.props as TeamProps).team);
const actor = computed(() => (page.props as TeamProps).actor);
const open = ref(false);

const items = computed(() =>
    team.value
        ? [
              {
                  label: '概要',
                  href: `/t/${team.value.slug}/dashboard`,
                  icon: LayoutDashboard,
              },
              {
                  label: '選手',
                  href: `/t/${team.value.slug}/athletes`,
                  icon: Users,
              },
              {
                  label: 'プログラム',
                  href: '#',
                  icon: ClipboardList,
                  disabled: true,
              },
              { label: 'レポート', href: '#', icon: BarChart3, disabled: true },
              {
                  label: 'チーム設定',
                  href: '#',
                  icon: Settings,
                  disabled: true,
              },
          ]
        : [],
);

function logout(): void {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 font-sans text-slate-900">
        <button
            class="fixed top-4 left-4 z-40 rounded-lg border bg-white p-2 shadow-sm lg:hidden"
            aria-label="メニューを開く"
            @click="open = true"
        >
            <Menu :size="20" />
        </button>
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-slate-950/30 lg:hidden"
            @click="open = false"
        />
        <aside
            :class="open ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform lg:translate-x-0"
        >
            <div class="flex h-16 items-center justify-between border-b px-5">
                <div>
                    <p
                        class="text-xs font-semibold tracking-[.18em] text-violet-600 uppercase"
                    >
                        Clear Dawn
                    </p>
                    <p class="font-semibold">Teams</p>
                </div>
                <button
                    class="lg:hidden"
                    aria-label="閉じる"
                    @click="open = false"
                >
                    <X :size="20" />
                </button>
            </div>
            <div
                v-if="team"
                class="m-4 rounded-xl border border-slate-200 bg-slate-50 p-3"
            >
                <p class="text-xs text-slate-500">現在のチーム</p>
                <p class="mt-1 truncate text-sm font-semibold">
                    {{ team.name }}
                </p>
            </div>
            <nav
                class="flex-1 space-y-1 px-3"
                aria-label="チームナビゲーション"
            >
                <component
                    :is="item.disabled ? 'span' : Link"
                    v-for="item in items"
                    :key="item.label"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium"
                    :class="
                        item.disabled
                            ? 'cursor-not-allowed text-slate-300'
                            : 'text-slate-600 hover:bg-violet-50 hover:text-violet-700'
                    "
                    @click="open = false"
                    ><component :is="item.icon" :size="18" />{{ item.label
                    }}<span v-if="item.disabled" class="ml-auto text-[10px]"
                        >準備中</span
                    ></component
                >
            </nav>
            <div class="border-t p-4">
                <p class="truncate text-sm font-medium">{{ actor?.name }}</p>
                <p class="truncate text-xs text-slate-500">
                    {{ actor?.email }}
                </p>
                <button
                    class="mt-3 flex items-center gap-2 text-sm text-slate-600 hover:text-violet-700"
                    @click="logout"
                >
                    <LogOut :size="16" />ログアウト
                </button>
            </div>
        </aside>
        <main class="min-h-screen lg:pl-64">
            <header
                class="flex h-16 items-center border-b border-slate-200 bg-white px-16 lg:px-8"
            >
                <p class="text-sm font-medium text-slate-600">
                    {{ team?.name ?? 'チーム選択' }}
                </p>
            </header>
            <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8"><slot /></div>
        </main>
    </div>
</template>
