<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ShieldCheck, Users } from '@lucide/vue';
defineProps<{
    googleEnabled: boolean;
    demoEnabled: boolean;
    errors?: { google?: string };
}>();
</script>
<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
        <Head title="チームログイン" />
        <div
            class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm"
        >
            <div class="mb-8 flex items-center gap-3">
                <div class="rounded-xl bg-violet-100 p-3 text-violet-700">
                    <Users />
                </div>
                <div>
                    <p
                        class="text-xs font-semibold tracking-[.16em] text-violet-600 uppercase"
                    >
                        Clear Dawn
                    </p>
                    <h1 class="text-xl font-bold">チームワークスペース</h1>
                </div>
            </div>
            <p class="text-sm leading-6 text-slate-600">
                コーチ・管理者・専門スタッフ専用です。選手本人のClear
                Dawnアカウントとは分離されています。
            </p>
            <div
                v-if="errors?.google"
                role="alert"
                class="mt-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
            >
                {{ errors.google }}
            </div>
            <Link
                v-if="googleEnabled"
                href="/auth/google"
                class="mt-6 flex w-full items-center justify-center rounded-lg bg-violet-600 px-4 py-3 font-semibold text-white hover:bg-violet-700 focus:ring-2 focus:ring-violet-500 focus:ring-offset-2"
                >Googleでログイン</Link
            >
            <div
                v-else
                class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
            >
                Google認証は未設定です。
            </div>
            <button
                v-if="demoEnabled"
                class="mt-3 w-full rounded-lg border border-slate-300 px-4 py-3 text-sm font-semibold hover:bg-slate-50"
                @click="router.post('/demo-login')"
            >
                ローカルデモで確認
            </button>
            <div
                class="mt-6 flex gap-2 border-t pt-5 text-xs leading-5 text-slate-500"
            >
                <ShieldCheck :size="32" class="shrink-0 text-emerald-600" />
                <p>
                    Google認証だけではアクセスできません。有効な招待またはチーム所属が必要です。
                </p>
            </div>
        </div>
    </div>
</template>
