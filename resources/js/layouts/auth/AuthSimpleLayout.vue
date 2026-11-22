<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { login, register } from '@/routes';

/**
 * App-style sign-in shell (docs/product/ios-app.md): washi background, brand,
 * and a ログイン / 新規登録 switch on those two screens. Used in the iOS app
 * and the browser alike.
 */
defineProps<{
    title?: string;
    description?: string;
    /** Shows the login / register switch with this tab selected. */
    authTab?: 'login' | 'register';
}>();
</script>

<template>
    <div class="cd-auth">
        <div class="cd-auth-inner">
            <header class="cd-auth-brand">
                <span class="cd-auth-rule" aria-hidden="true" />
                <p class="cd-auth-wordmark">Clear Dawn</p>
                <p class="cd-auth-tagline">今日を整える、静かな記録帳</p>
            </header>

            <main class="cd-auth-card">
                <nav
                    v-if="authTab"
                    class="cd-auth-tabs"
                    aria-label="ログインと新規登録の切り替え"
                >
                    <Link
                        :href="login()"
                        :aria-current="authTab === 'login' ? 'page' : undefined"
                        >ログイン</Link
                    >
                    <Link
                        :href="register()"
                        :aria-current="
                            authTab === 'register' ? 'page' : undefined
                        "
                        >新規登録</Link
                    >
                </nav>

                <div class="cd-auth-heading">
                    <h1>{{ title }}</h1>
                    <p v-if="description">{{ description }}</p>
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
