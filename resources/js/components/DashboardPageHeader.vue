<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import { index as lifeAreasIndex } from '@/routes/life-areas';

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
    <div
        class="flex min-w-0 flex-1 flex-col gap-4 py-3 md:flex-row md:items-center md:justify-between md:gap-6 md:py-4"
    >
        <div class="flex min-w-0 flex-col items-start gap-2">
            <h1
                class="font-serif text-[2rem] leading-none font-normal tracking-[0.14em] text-cd-dawn-deep md:text-[2.5rem]"
            >
                Clear Dawn
            </h1>
            <div
                aria-hidden="true"
                class="cd-mask-ornament h-5 w-48 text-cd-gilt md:w-64"
            />
            <p
                class="font-sans text-xs tracking-[0.08em] text-cd-ink-muted md:text-sm"
            >
                多忙な中、思考を整理し、夜明けへ導く。
            </p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-3 md:gap-4">
            <time
                :datetime="todayIso"
                class="cursor-default font-serif text-base tracking-[0.12em] text-cd-ink lining-nums select-none md:text-lg"
            >
                {{ today }}
            </time>

            <Link
                :href="lifeAreasIndex()"
                aria-label="領域管理"
                class="group inline-flex items-center gap-1.5 font-serif text-base tracking-[0.12em] text-cd-ink-muted transition-colors hover:text-cd-dawn-deep md:text-lg"
            >
                <SlidersHorizontal
                    :size="16"
                    :stroke-width="1.6"
                    class="opacity-70 transition-opacity group-hover:opacity-100"
                    aria-hidden="true"
                />
                <span class="underline-offset-4 group-hover:underline">
                    領域管理
                </span>
            </Link>

            <div
                aria-hidden="true"
                class="cd-header-divider hidden h-5 sm:block"
            />

            <HeaderUserMenu compact />
        </div>
    </div>
</template>
