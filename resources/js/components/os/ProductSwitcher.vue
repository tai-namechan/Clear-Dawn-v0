<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, Compass, Library, Sun } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
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

const hoverByKey: Record<ProductKey, string> = {
    clear_dawn:
        'hover:ring-2 hover:ring-cd-dawn-deep/45 hover:bg-cd-dawn-deep/8',
    yoyu: 'hover:ring-2 hover:ring-os-yoyu/45 hover:bg-os-yoyu/10',
    kioku: 'hover:ring-2 hover:ring-os-kioku/45 hover:bg-os-kioku/10',
};

const washByKey: Record<ProductKey, string> = {
    clear_dawn: 'bg-cd-dawn-deep/12',
    yoyu: 'bg-os-yoyu/14',
    kioku: 'bg-os-kioku/14',
};

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

// プレビュー画像をマウント時に先読みし、モーダルを開いた瞬間に表示できるようにする
onMounted(() => {
    products.value.forEach((product) => {
        const image = new Image();
        image.src = previewByKey[product.key].src;
    });
});

// モーダルを開いた時点で他プロダクトのページ（JSチャンク + props）を先読みし、
// カードを押してからの初回遷移待ちを消す
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    products.value
        .filter((product) => product.key !== currentProductKey.value)
        .forEach((product) => {
            router.prefetch(
                product.href,
                { method: 'get' },
                { cacheFor: '1m' },
            );
        });
});
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
                        class="group relative overflow-hidden rounded-xl bg-muted/20 text-left transition-[box-shadow,background-color,transform] duration-200 hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="hoverByKey[product.key]"
                        :data-test="`product-card-${product.key}`"
                        :aria-current="
                            product.key === currentProductKey
                                ? 'true'
                                : undefined
                        "
                        @click="selectProduct(product)"
                    >
                        <span class="sr-only">
                            {{ product.name }} — {{ product.tagline }}
                        </span>
                        <img
                            :src="previewByKey[product.key].src"
                            :alt="previewByKey[product.key].alt"
                            class="h-auto w-full object-contain transition-[filter] duration-200 group-hover:brightness-[1.03]"
                            loading="eager"
                            decoding="async"
                        />
                        <span
                            class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                            :class="washByKey[product.key]"
                            aria-hidden="true"
                        />
                        <span
                            v-if="product.key === currentProductKey"
                            class="absolute top-2 right-2 z-10 inline-flex items-center gap-1 rounded-full bg-cd-ink/85 px-2.5 py-1 text-[10px] font-semibold text-cd-surface shadow-sm backdrop-blur-sm"
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
