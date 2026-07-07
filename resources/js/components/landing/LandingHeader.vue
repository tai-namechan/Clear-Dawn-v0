<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { login, register } from '@/routes';
</script>

<template>
    <header
        class="relative z-10 flex w-full justify-end px-6 py-5 md:px-10 md:py-6"
    >
        <nav
            aria-label="公開ページナビゲーション"
            class="flex items-center gap-4 md:gap-5"
        >
            <Link
                :href="login()"
                class="font-sans text-sm tracking-[0.08em] text-white/88 underline-offset-4 transition-colors hover:text-white hover:underline md:text-[0.95rem]"
            >
                ログイン
            </Link>
            <Link
                :href="register()"
                class="rounded-full border border-white/35 bg-white/10 px-4 py-1.5 font-sans text-sm tracking-[0.08em] text-white/92 backdrop-blur-[2px] transition-colors hover:border-white/55 hover:bg-white/16 md:px-5 md:text-[0.95rem]"
            >
                新規登録
            </Link>
        </nav>
    </header>
</template>
