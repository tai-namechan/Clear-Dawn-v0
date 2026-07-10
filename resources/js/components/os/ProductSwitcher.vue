<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, Compass, Library, Sun } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ProductDefinition, ProductKey } from '@/types/product';

const page = usePage();
const open = ref(false);

const products = computed(() => page.props.products as ProductDefinition[]);
const currentProductKey = computed(
    () => page.props.currentProduct as ProductKey,
);

const currentProduct = computed(
    () =>
        products.value.find((p) => p.key === currentProductKey.value) ??
        products.value[0],
);

const accentByKey: Record<ProductKey, string> = {
    clear_dawn: 'text-cd-dawn-deep',
    yoyu: 'text-os-yoyu',
    kioku: 'text-os-kioku',
};

const ringByKey: Record<ProductKey, string> = {
    clear_dawn: 'ring-cd-dawn-deep/30',
    yoyu: 'ring-os-yoyu/30',
    kioku: 'ring-os-kioku/30',
};

const iconByKey = {
    clear_dawn: Compass,
    yoyu: Sun,
    kioku: Library,
} as const;

const previewHintByKey: Record<ProductKey, string> = {
    clear_dawn: 'マトリクスで人生の方針を整理',
    yoyu: '今日の予定・余裕・秘書',
    kioku: '記憶の保存・検索・想起',
};

function selectProduct(product: ProductDefinition): void {
    if (product.key === currentProductKey.value) {
        open.value = false;
        return;
    }

    open.value = false;
    router.visit(product.href);
}
</script>

<template>
    <div v-if="currentProduct" class="shrink-0">
        <button
            type="button"
            data-test="product-switcher-trigger"
            class="inline-flex items-center gap-1.5 rounded-full border border-cd-line bg-cd-surface/80 px-3 py-1.5 text-sm font-medium text-cd-ink shadow-sm transition-colors hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :aria-expanded="open"
            aria-haspopup="dialog"
            @click="open = true"
        >
            <component
                :is="iconByKey[currentProduct.key]"
                :size="14"
                :stroke-width="2"
                class="shrink-0 opacity-80"
                :class="accentByKey[currentProduct.key]"
                aria-hidden="true"
            />
            <span class="max-w-[9rem] truncate tracking-[0.04em]">
                {{ currentProduct.name }}
            </span>
            <ChevronDown
                :size="14"
                class="shrink-0 text-cd-ink-muted opacity-70"
                aria-hidden="true"
            />
        </button>

        <Dialog :open="open" @update:open="open = $event">
            <DialogContent
                class="max-h-[min(90vh,40rem)] gap-0 overflow-y-auto border-cd-line bg-cd-surface p-0 sm:max-w-3xl"
                data-test="product-switcher-modal"
            >
                <DialogHeader class="border-b border-cd-line px-6 py-5 text-left">
                    <DialogTitle class="text-lg font-semibold text-cd-ink">
                        プロダクト切り替え
                    </DialogTitle>
                    <DialogDescription class="text-sm text-cd-ink-muted">
                        目的に合わせてプロダクトを切り替えられます
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="grid gap-4 p-5 sm:grid-cols-3 sm:gap-3 sm:p-6"
                    role="list"
                >
                    <button
                        v-for="product in products"
                        :key="product.key"
                        type="button"
                        role="listitem"
                        class="flex flex-col rounded-xl border border-cd-line bg-background p-4 text-left shadow-sm transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            product.key === currentProductKey
                                ? `ring-2 ${ringByKey[product.key]}`
                                : ''
                        "
                        :data-test="`product-card-${product.key}`"
                        @click="selectProduct(product)"
                    >
                        <div class="mb-3 flex items-start justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <span
                                    class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-muted"
                                    :class="accentByKey[product.key]"
                                >
                                    <component
                                        :is="iconByKey[product.key]"
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div class="min-w-0">
                                    <div
                                        class="truncate text-sm font-semibold text-cd-ink"
                                    >
                                        {{ product.name }}
                                    </div>
                                    <div
                                        class="truncate text-xs text-cd-ink-muted"
                                    >
                                        {{ product.tagline }}
                                    </div>
                                </div>
                            </div>
                            <span
                                v-if="product.key === currentProductKey"
                                class="inline-flex shrink-0 items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-cd-ink"
                                data-test="product-current-badge"
                            >
                                <Check :size="12" aria-hidden="true" />
                                利用中
                            </span>
                        </div>

                        <div
                            class="flex min-h-24 flex-1 items-center justify-center rounded-lg border border-dashed border-cd-line bg-muted/40 px-3 py-4 text-center text-xs leading-relaxed text-cd-ink-muted"
                            aria-hidden="true"
                        >
                            {{ previewHintByKey[product.key] }}
                        </div>
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
