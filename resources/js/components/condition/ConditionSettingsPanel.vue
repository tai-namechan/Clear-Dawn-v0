<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div aria-label="表示の考え方">
        <h2 class="font-sans text-base font-semibold text-cd-ink">
            表示の考え方
        </h2>
        <ul class="mt-3 space-y-2 font-sans text-sm text-cd-ink-muted">
            <li>・「今日」は入力専用。大きな空グラフは出しません</li>
            <li>・「推移」は分析専用。チャートと前日比だけを見ます</li>
            <li>・30秒チェックインは作戦カードの判断材料になります</li>
        </ul>
        <Link
            href="/today"
            class="mt-4 inline-flex font-sans text-sm font-medium text-primary underline-offset-2 hover:underline"
        >
            今日/作戦へ移動
        </Link>
    </div>
</template>
