<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'プロフィール設定',
                href: edit(),
            },
        ],
    },
});

interface Props {
    mustVerifyEmail: boolean;
    emailVerified: boolean;
    status?: string;
}

defineProps<Props>();

const page = usePage();
const user = computed(() => page.props.auth.user!);
</script>

<template>
    <Head title="プロフィール設定" />

    <h1 class="sr-only">プロフィール設定</h1>

    <div class="flex flex-col space-y-12">
        <div class="cd-panel p-6 md:p-8">
            <Heading
                variant="small"
                title="プロフィール"
                description="アカウントの基本情報を管理します。"
                class="mb-6"
            />

            <Form
                v-bind="ProfileController.update.form()"
                class="space-y-6 font-sans"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-2">
                    <Label for="name" class="text-cd-ink">名前</Label>
                    <Input
                        id="name"
                        class="mt-1 block w-full"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        placeholder="お名前"
                    />
                    <p class="font-sans text-sm text-cd-ink-muted">
                        あなたの名前を入力してください。ダッシュボードやメモで表示されます。
                    </p>
                    <InputError class="mt-1" :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email" class="text-cd-ink"
                        >メールアドレス</Label
                    >
                    <Input
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        name="email"
                        :default-value="user.email"
                        required
                        autocomplete="username"
                        placeholder="メールアドレス"
                    />
                    <p class="font-sans text-sm text-cd-ink-muted">
                        ログインや各種通知に使用されるメールアドレスです。
                    </p>
                    <InputError class="mt-1" :message="errors.email" />
                </div>

                <div v-if="mustVerifyEmail && !emailVerified">
                    <p class="-mt-2 text-sm text-cd-ink-muted">
                        メールアドレスが未確認です。
                        <Link
                            :href="send()"
                            as="button"
                            class="text-cd-ink underline decoration-cd-line underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                        >
                            確認メールを再送する
                        </Link>
                    </p>

                    <div
                        v-if="status === 'verification-link-sent'"
                        class="mt-2 text-sm font-medium text-cd-moss"
                    >
                        新しい確認リンクをメールアドレスに送信しました。
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        class="font-sans tracking-[0.08em]"
                        data-test="update-profile-button"
                    >
                        保存する
                    </Button>
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="recentlySuccessful"
                            class="text-sm text-cd-ink-muted"
                        >
                            保存しました。
                        </p>
                    </Transition>
                </div>
            </Form>
        </div>

        <DeleteUser />
    </div>
</template>
