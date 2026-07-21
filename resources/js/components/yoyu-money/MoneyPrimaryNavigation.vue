<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    moneyPrimaryNav,
    resolveMoneyPrimaryNav,
} from '@/lib/yoyuMoney/navigation';

interface Props {
    active?: string;
}

const props = defineProps<Props>();
const page = usePage();

const activeKey = computed(() => {
    if (props.active) {
        return props.active;
    }

    return resolveMoneyPrimaryNav(page.url);
});
</script>

<template>
    <nav
        aria-label="お金の余裕メニュー"
        class="flex items-center gap-1 overflow-x-auto pb-0.5 md:flex-wrap md:overflow-visible"
    >
        <Link
            v-for="item in moneyPrimaryNav"
            :key="item.key"
            :href="item.href"
            class="shrink-0 rounded-lg px-3 py-2 text-[13px] font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
            :class="
                activeKey === item.key
                    ? 'bg-os-yoyu text-white'
                    : 'text-os-sub hover:bg-os-yoyu-soft hover:text-os-ink'
            "
            :aria-current="activeKey === item.key ? 'page' : undefined"
        >
            {{ item.label }}
        </Link>
        <div class="ml-auto shrink-0">
            <Link
                href="/yoyu/money/settings"
                class="inline-flex size-9 items-center justify-center rounded-lg text-os-sub transition-colors hover:bg-os-yoyu-soft hover:text-os-ink focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                :aria-current="activeKey === 'settings' ? 'page' : undefined"
                aria-label="設定"
                title="設定"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    class="size-5"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                </svg>
            </Link>
        </div>
    </nav>
</template>
