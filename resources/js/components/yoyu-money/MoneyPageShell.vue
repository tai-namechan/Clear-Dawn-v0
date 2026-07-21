<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import MoneyPrimaryNavigation from '@/components/yoyu-money/MoneyPrimaryNavigation.vue';
import MoneySectionTabs from '@/components/yoyu-money/MoneySectionTabs.vue';
import { formatMonthLabel, shiftMonth } from '@/lib/yoyuMoney/labels';
import {
    moneyPrimaryNav,
    moneyRecordMenu,
    resolveMoneyPrimaryNav,
} from '@/lib/yoyuMoney/navigation';
import type { MoneySectionTab } from '@/lib/yoyuMoney/navigation';

interface Props {
    title: string;
    documentTitle?: string;
    month?: string | null;
    asOf?: string | null;
    showMonthSwitcher?: boolean;
    showRecordMenu?: boolean;
    sectionTabs?: MoneySectionTab[];
    sectionActive?: string;
    sectionLabel?: string;
    primaryActive?: string;
}

const props = withDefaults(defineProps<Props>(), {
    documentTitle: undefined,
    month: null,
    asOf: null,
    showMonthSwitcher: false,
    showRecordMenu: true,
    sectionTabs: undefined,
    sectionActive: undefined,
    sectionLabel: 'セクション',
    primaryActive: undefined,
});

const page = usePage();
const recordOpen = ref(false);
const recordRoot = ref<HTMLElement | null>(null);

const pageTitle = computed(
    () => props.documentTitle ?? `${props.title} — お金の余裕`,
);

const resolvedPrimary = computed(
    () => props.primaryActive ?? resolveMoneyPrimaryNav(page.url),
);

function goMonth(delta: number): void {
    if (!props.month) {
        return;
    }

    const next = shiftMonth(props.month, delta);
    router.get(
        window.location.pathname,
        { month: next },
        { preserveState: true, preserveScroll: true },
    );
}

function onDocumentClick(event: MouseEvent): void {
    if (!recordOpen.value || !recordRoot.value) {
        return;
    }

    if (!recordRoot.value.contains(event.target as Node)) {
        recordOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div class="mx-auto w-full max-w-[1280px] space-y-4 pb-20 md:pb-6">
        <Head :title="pageTitle" />

        <header class="space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p
                        class="text-[12px] font-semibold tracking-wide text-os-yoyu"
                    >
                        お金の余裕
                    </p>
                    <h1
                        class="truncate text-xl font-bold text-os-ink md:text-2xl"
                    >
                        {{ title }}
                    </h1>
                </div>

                <div v-if="showRecordMenu" ref="recordRoot" class="relative">
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-lg bg-os-yoyu px-3.5 py-2 text-[13px] font-semibold text-white shadow-sm transition hover:bg-os-yoyu/90 focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        :aria-expanded="recordOpen"
                        aria-haspopup="menu"
                        @click="recordOpen = !recordOpen"
                    >
                        ＋記録する
                    </button>
                    <div
                        v-if="recordOpen"
                        role="menu"
                        class="absolute right-0 z-30 mt-2 w-48 overflow-hidden rounded-xl border border-os-line bg-white py-1 shadow-lg"
                    >
                        <Link
                            v-for="item in moneyRecordMenu"
                            :key="item.key"
                            :href="item.href"
                            role="menuitem"
                            class="block px-3 py-2.5 text-[13px] text-os-ink hover:bg-os-yoyu-soft focus-visible:bg-os-yoyu-soft focus-visible:outline-none"
                            @click="recordOpen = false"
                        >
                            {{ item.label }}
                        </Link>
                    </div>
                </div>
            </div>

            <MoneyPrimaryNavigation :active="resolvedPrimary" />

            <div
                v-if="showMonthSwitcher && month"
                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-os-line bg-white px-3 py-2.5"
            >
                <div>
                    <p class="text-[12px] text-os-sub">対象月</p>
                    <p class="text-[15px] font-bold text-os-ink">
                        {{ formatMonthLabel(month) }}
                    </p>
                    <p v-if="asOf" class="text-[12px] text-os-faint">
                        基準日 {{ asOf }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="min-h-10 rounded-lg border border-os-line px-3 text-[13px] font-semibold text-os-sub hover:bg-os-yoyu-soft focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        @click="goMonth(-1)"
                    >
                        ＜ 前月
                    </button>
                    <button
                        type="button"
                        class="min-h-10 rounded-lg border border-os-line px-3 text-[13px] font-semibold text-os-sub hover:bg-os-yoyu-soft focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        @click="goMonth(1)"
                    >
                        次月 ＞
                    </button>
                </div>
            </div>

            <MoneySectionTabs
                v-if="sectionTabs?.length && sectionActive"
                :tabs="sectionTabs"
                :active="sectionActive"
                :label="sectionLabel"
            />

            <div
                v-if="$slots.actions"
                class="flex flex-wrap items-center gap-2"
            >
                <slot name="actions" />
            </div>
        </header>

        <slot />

        <!-- Mobile bottom navigation -->
        <nav
            aria-label="お金モバイルナビ"
            class="fixed inset-x-0 bottom-0 z-40 border-t border-os-line bg-os-yoyu-bg/95 backdrop-blur-sm md:hidden"
        >
            <ul
                class="mx-auto flex max-w-[1280px] items-stretch justify-around px-1 py-1"
            >
                <li
                    v-for="item in moneyPrimaryNav"
                    :key="item.key"
                    class="flex-1"
                >
                    <Link
                        :href="item.href"
                        class="flex min-h-12 flex-col items-center justify-center px-1 text-[11px] font-semibold"
                        :class="
                            resolvedPrimary === item.key
                                ? 'text-os-yoyu'
                                : 'text-os-sub'
                        "
                    >
                        {{ item.label }}
                    </Link>
                </li>
            </ul>
        </nav>
    </div>
</template>
