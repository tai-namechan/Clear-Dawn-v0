<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
defineProps<{ teams: Array<{ name: string; slug: string }> }>();
</script>
<template>
    <div>
        <Head title="チーム選択" />
        <h1 class="text-2xl font-bold">チームを選択</h1>
        <p class="mt-2 text-sm text-slate-500">
            アクセスするワークスペースを選んでください。
        </p>
        <div v-if="teams.length" class="mt-6 grid gap-4 sm:grid-cols-2">
            <Link
                v-for="team in teams"
                :key="team.slug"
                :href="`/t/${team.slug}/dashboard`"
                class="rounded-xl border bg-white p-5 shadow-sm hover:border-violet-300"
                ><p class="font-semibold">{{ team.name }}</p>
                <p class="mt-1 text-sm text-violet-600">
                    ワークスペースを開く →
                </p></Link
            >
        </div>
        <div
            v-else
            class="mt-6 rounded-xl border border-dashed bg-white p-10 text-center"
        >
            <p class="font-semibold">利用できるチームがありません</p>
            <p class="mt-2 text-sm text-slate-500">
                管理者からの招待を確認してください。
            </p>
        </div>
    </div>
</template>
