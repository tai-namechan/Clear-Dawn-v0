<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
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
        class="grid w-full grid-cols-[auto_minmax(0,1fr)] gap-x-2 px-4 py-3 md:gap-x-3 md:px-6 md:py-4"
    >
        <SidebarTrigger
            class="col-start-1 row-start-1 mt-1.5 shrink-0 self-start md:mt-2"
        />

        <div
            class="col-start-2 row-start-1 flex min-w-0 flex-col gap-3 lg:flex-row lg:items-center lg:justify-between lg:gap-4"
        >
            <h1
                class="font-serif text-[2rem] leading-none font-normal tracking-[0.14em] text-cd-dawn-deep md:text-[2.5rem]"
            >
                Clear Dawn
            </h1>

            <div class="flex shrink-0 items-center gap-3 md:gap-4">
                <div
                    class="cd-frost flex items-center gap-3 rounded-full border border-cd-line/80 px-4 py-2 shadow-sm md:gap-4 md:px-5"
                >
                    <time
                        :datetime="todayIso"
                        class="cursor-default font-serif text-base tracking-[0.12em] text-cd-ink lining-nums select-none md:text-lg"
                    >
                        {{ today }}
                    </time>

                    <div aria-hidden="true" class="cd-header-divider h-5" />

                    <Link
                        :href="lifeAreasIndex()"
                        aria-label="領域管理"
                        class="group inline-flex items-center gap-1.5 font-serif text-base tracking-[0.12em] text-cd-ink transition-colors hover:text-cd-dawn-deep md:text-lg"
                    >
                        <SlidersHorizontal
                            :size="16"
                            :stroke-width="1.6"
                            class="opacity-80 transition-opacity group-hover:opacity-100"
                            aria-hidden="true"
                        />
                        <span class="underline-offset-4 group-hover:underline">
                            領域管理
                        </span>
                    </Link>
                </div>

                <div
                    aria-hidden="true"
                    class="cd-header-divider hidden h-5 sm:block"
                />

                <HeaderUserMenu compact />
            </div>
        </div>

        <div
            class="col-start-2 row-start-2 flex flex-col items-start gap-2 pt-2"
        >
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
    </div>
</template>
