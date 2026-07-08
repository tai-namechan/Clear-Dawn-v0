<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="危険な操作ゾーン"
            description="アカウントの削除など、元に戻せない操作を行います。"
        />
        <div
            class="flex flex-col gap-4 rounded-2xl border border-cd-danger-line bg-cd-danger-surface p-5 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <TriangleAlert
                    :size="20"
                    :stroke-width="1.7"
                    aria-hidden="true"
                    class="mt-0.5 shrink-0 text-cd-danger-ink"
                />
                <div class="space-y-1">
                    <p class="font-medium text-cd-danger-ink">
                        アカウントを削除する
                    </p>
                    <p class="text-sm text-cd-ink-muted">
                        アカウントとすべてのデータが完全に削除されます。<br />
                        この操作は取り消せません。
                    </p>
                </div>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="outline"
                        class="shrink-0 border-cd-danger-line font-sans tracking-[0.08em] text-cd-danger-ink hover:bg-cd-danger-surface hover:text-cd-danger-ink"
                        data-test="delete-user-button"
                    >
                        アカウントを削除
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle
                                >本当にアカウントを削除しますか？</DialogTitle
                            >
                            <DialogDescription>
                                アカウントを削除すると、すべてのデータが完全に削除され、元に戻すことはできません。削除を確定するには、パスワードを入力してください。
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only"
                                >パスワード</Label
                            >
                            <PasswordInput
                                id="password"
                                name="password"
                                ref="passwordInput"
                                placeholder="パスワード"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    キャンセル
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                アカウントを削除
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
