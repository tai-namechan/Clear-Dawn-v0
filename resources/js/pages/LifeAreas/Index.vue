<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Eye,
    EyeOff,
    GripVertical,
    Pencil,
    Plus,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    lifeAreaColorClasses,
    lifeAreaColorOptions,
} from '@/lib/lifeAreaColors';
import type { LifeArea, LifeAreaColor } from '@/types/matrix';
import { dashboard } from '@/routes';
import { destroy, reorder, restore, store, update } from '@/routes/life-areas';

interface Props {
    lifeAreas: LifeArea[];
}

const props = defineProps<Props>();

const editingId = ref<string | null>(null);
const editingColor = ref<LifeAreaColor>('dawn');
const newColor = ref<LifeAreaColor>('dawn');

function startEditing(area: LifeArea): void {
    editingId.value = area.id;
    editingColor.value = area.color;
}

function move(index: number, direction: -1 | 1): void {
    const ids = props.lifeAreas.map((area) => area.id);
    const target = index + direction;

    if (target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target], ids[index]];

    router.patch(reorder.url(), { ordered_ids: ids }, { preserveScroll: true });
}

function deactivate(area: LifeArea): void {
    router.delete(destroy.url(area.id), { preserveScroll: true });
}

function reactivate(area: LifeArea): void {
    router.patch(restore.url(area.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="領域管理" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <PageTitleOrnament
                    title="領域管理"
                    subtitle="人生を構成する領域を整え、優先順位とバランスを明確にしましょう。"
                    align="left"
                />

                <Link
                    :href="dashboard()"
                    class="mt-2 flex shrink-0 items-center gap-2 rounded-full border border-cd-line/80 bg-white/60 px-3.5 py-1.5 font-sans text-sm text-cd-ink-muted transition-colors hover:border-cd-line hover:text-cd-ink"
                >
                    <ArrowLeft
                        :size="16"
                        :stroke-width="1.6"
                        aria-hidden="true"
                    />
                    ダッシュボードへ戻る
                </Link>
            </div>

            <section
                aria-label="登録済みの領域"
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface"
            >
                <h2
                    class="border-b border-cd-line/60 px-5 py-4 font-serif text-base tracking-[0.12em] text-cd-ink"
                >
                    登録済みの領域
                </h2>
                <ul class="flex flex-col">
                    <li
                        v-for="(area, index) in lifeAreas"
                        :key="area.id"
                        class="border-b border-cd-line/60 px-5 py-4 last:border-b-0"
                        :class="{ 'opacity-55': !area.is_active }"
                    >
                        <Form
                            v-if="editingId === area.id"
                            v-bind="update.form(area.id)"
                            :options="{ preserveScroll: true }"
                            class="flex flex-col gap-3"
                            v-slot="{ errors, processing }"
                            @success="editingId = null"
                        >
                            <Input
                                name="name"
                                :default-value="area.name"
                                required
                                maxlength="50"
                                placeholder="領域名"
                            />
                            <InputError :message="errors.name" />

                            <input
                                type="hidden"
                                name="color"
                                :value="editingColor"
                            />
                            <div
                                class="flex flex-wrap gap-2"
                                role="radiogroup"
                                aria-label="色を選択"
                            >
                                <button
                                    v-for="option in lifeAreaColorOptions"
                                    :key="option.value"
                                    type="button"
                                    role="radio"
                                    :aria-checked="
                                        editingColor === option.value
                                    "
                                    :aria-label="option.label"
                                    class="size-7 rounded-full border border-cd-line transition-shadow"
                                    :class="[
                                        lifeAreaColorClasses[option.value],
                                        editingColor === option.value
                                            ? 'ring-2 ring-ring ring-offset-2'
                                            : '',
                                    ]"
                                    @click="editingColor = option.value"
                                />
                            </div>
                            <InputError :message="errors.color" />

                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="editingId = null"
                                >
                                    キャンセル
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="processing"
                                >
                                    保存
                                </Button>
                            </div>
                        </Form>

                        <div
                            v-else
                            class="flex items-center justify-between gap-3"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    aria-hidden="true"
                                    class="shrink-0 cursor-grab text-cd-ink-muted/40"
                                >
                                    <GripVertical
                                        :size="16"
                                        :stroke-width="1.6"
                                    />
                                </span>
                                <span
                                    aria-hidden="true"
                                    class="size-4 shrink-0 rounded-full border border-cd-line"
                                    :class="lifeAreaColorClasses[area.color]"
                                />
                                <span
                                    class="min-w-0 truncate font-serif text-base tracking-[0.08em] text-cd-ink"
                                >
                                    {{ area.name }}
                                </span>
                                <span
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs"
                                    :class="
                                        area.is_active
                                            ? 'bg-cd-moss/15 text-cd-moss'
                                            : 'bg-muted text-cd-ink-muted'
                                    "
                                >
                                    <component
                                        :is="area.is_active ? Eye : EyeOff"
                                        :size="12"
                                        :stroke-width="1.8"
                                        aria-hidden="true"
                                    />
                                    {{ area.is_active ? '公開中' : '非公開' }}
                                </span>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :disabled="index === 0"
                                    :aria-label="`${area.name} を上へ`"
                                    @click="move(index, -1)"
                                >
                                    <ArrowUp :size="15" :stroke-width="1.6" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :disabled="index === lifeAreas.length - 1"
                                    :aria-label="`${area.name} を下へ`"
                                    @click="move(index, 1)"
                                >
                                    <ArrowDown :size="15" :stroke-width="1.6" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`${area.name} を編集`"
                                    @click="startEditing(area)"
                                >
                                    <Pencil :size="15" :stroke-width="1.6" />
                                </Button>
                                <Button
                                    v-if="area.is_active"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`${area.name} を非表示にする`"
                                    @click="deactivate(area)"
                                >
                                    <EyeOff :size="15" :stroke-width="1.6" />
                                </Button>
                                <Button
                                    v-else
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`${area.name} を再表示する`"
                                    @click="reactivate(area)"
                                >
                                    <Eye :size="15" :stroke-width="1.6" />
                                </Button>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>

            <section
                aria-label="新しい領域を追加"
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface px-5 py-5"
            >
                <h2
                    class="mb-4 font-serif text-base tracking-[0.12em] text-cd-ink"
                >
                    新しい領域を追加
                </h2>
                <Form
                    v-bind="store.form()"
                    :options="{ preserveScroll: true }"
                    reset-on-success
                    class="flex flex-col gap-3"
                    v-slot="{ errors, processing }"
                >
                    <Input
                        name="name"
                        required
                        maxlength="50"
                        placeholder="領域名（例: 読書）"
                    />
                    <InputError :message="errors.name" />

                    <input type="hidden" name="color" :value="newColor" />
                    <p class="font-sans text-sm text-cd-ink-muted">
                        カラーを選択
                    </p>
                    <div
                        class="flex flex-wrap gap-2"
                        role="radiogroup"
                        aria-label="色を選択"
                    >
                        <button
                            v-for="option in lifeAreaColorOptions"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :aria-checked="newColor === option.value"
                            :aria-label="option.label"
                            class="size-7 rounded-full border border-cd-line transition-shadow"
                            :class="[
                                lifeAreaColorClasses[option.value],
                                newColor === option.value
                                    ? 'ring-2 ring-ring ring-offset-2'
                                    : '',
                            ]"
                            @click="newColor = option.value"
                        />
                    </div>
                    <InputError :message="errors.color" />

                    <div class="flex justify-end pt-1">
                        <Button
                            type="submit"
                            class="font-sans tracking-[0.08em]"
                            :disabled="processing"
                        >
                            <Plus :size="16" :stroke-width="1.8" />
                            追加する
                        </Button>
                    </div>
                </Form>
            </section>
        </div>
    </div>
</template>
