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

const iconByKey = {
    clear_dawn: Compass,
    yoyu: Sun,
    kioku: Library,
} as const;

const previewByKey: Record<ProductKey, { src: string; alt: string }> = {
    clear_dawn: {
        src: '/images/products/clear-dawn.jpg',
        alt: 'Clear Dawn プレビュー — マトリクスで人生の方針を整理',
    },
    yoyu: {
        src: '/images/products/yoyu.jpg',
        alt: 'ヨユウ プレビュー — 焦らず、前へ回すAI秘書',
    },
    kioku: {
        src: '/images/products/kioku.jpg',
        alt: 'キオク プレビュー — 記憶の保存・検索・想起',
    },
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
                class="max-h-[min(95vh,58rem)] gap-0 overflow-y-auto border-cd-line bg-cd-surface p-0 sm:max-w-5xl"
                data-test="product-switcher-modal"
            >
                <DialogHeader class="sr-only">
                    <DialogTitle>プロダクト切り替え</DialogTitle>
                    <DialogDescription>
                        目的に合わせてプロダクトを切り替えられます
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="grid items-start gap-4 p-4 sm:grid-cols-3 sm:gap-5 sm:p-6"
                    role="list"
                >
                    <button
                        v-for="product in products"
                        :key="product.key"
                        type="button"
                        role="listitem"
                        class="group relative overflow-hidden rounded-xl bg-muted/20 text-left transition-opacity hover:opacity-95 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :data-test="`product-card-${product.key}`"
                        :aria-current="
                            product.key === currentProductKey
                                ? 'true'
                                : undefined
                        "
                        @click="selectProduct(product)"
                    >
                        <img
                            :src="previewByKey[product.key].src"
                            :alt="previewByKey[product.key].alt"
                            class="h-auto w-full object-contain"
                            loading="eager"
                            decoding="async"
                        />
                        <div
                            class="absolute inset-x-0 top-0 bg-gradient-to-b from-cd-surface/95 via-cd-surface/88 to-transparent px-3 pt-3 pb-8"
                        >
                            <div class="pr-16 text-sm font-semibold text-cd-ink">
                                {{ product.name }}
                            </div>
                            <div class="mt-0.5 text-xs leading-snug text-cd-ink-muted">
                                {{ product.tagline }}
                            </div>
                        </div>
                        <span
                            v-if="product.key === currentProductKey"
                            class="absolute top-2 right-2 inline-flex items-center gap-1 rounded-full bg-cd-ink/85 px-2.5 py-1 text-[10px] font-semibold text-cd-surface shadow-sm backdrop-blur-sm"
                            data-test="product-current-badge"
                        >
                            <Check :size="12" aria-hidden="true" />
                            利用中
                        </span>
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
