---
name: vue-sfc-patterns
description: >-
  Vue 3 SFC + Inertia + TypeScript プロジェクトのパターンガイド。.vue ファイルの作成・編集、
  defineProps / defineEmits / defineModel / defineExpose の使い方、
  Vitest での Vue コンポーネントテスト、Inertia のデータフローのコード生成時に使用する。
---

# Vue SFC Patterns（Laravel + Inertia + Vue 3 + TypeScript）

## Tech Stack

- Vue 3 + `<script setup lang="ts">`（Composition API 専用、Options API 禁止）
- Inertia.js v3 (`@inertiajs/vue3`) — SPA ルーティング・ページ props
- TypeScript（strict: `tsconfig.json` の `strict: true`）
- Tailwind CSS v4（`resources/css/app.css` のデザイントークン）
- Laravel Wayfinder — 型安全なルート・フォーム定義（`@/routes/*`, `@/actions/*`）
- reka-ui + shadcn パターン — `resources/js/components/ui/`
- Vitest: **未導入**（フロント単体テスト追加時は本スキルのテスト節を参照）

## ディレクトリ構造

```
resources/js/
├── app.ts              # Inertia エントリポイント・レイアウト割当
├── pages/              # Inertia ページ（小文字ディレクトリ: auth/, settings/ 等）
├── components/         # 共有 UI コンポーネント
│   └── ui/             # reka-ui ベースのプリミティブ（原則内部改変禁止）
├── layouts/            # ページレイアウト（AppLayout, AuthLayout, settings/Layout 等）
├── composables/        # Composition API フック（useAppearance 等）
├── lib/                # ユーティリティ（utils.ts, flashToast.ts 等）
├── types/              # 型定義（auth.ts, navigation.ts 等）
├── routes/             # Wayfinder 自動生成（.gitignore 対象）
└── actions/            # Wayfinder 自動生成 Controller アクション（.gitignore 対象）
```

パスエイリアス（`tsconfig.json` の `paths`。Vite は tsconfig を参照）:

| エイリアス | 実パス |
|------------|--------|
| `@/*` | `resources/js/*` |

使用例:
- `@/components/ui/button` → `resources/js/components/ui/button`
- `@/pages/auth/Login` → Inertia ページ名 `auth/Login` に対応
- `@/routes/login` → Wayfinder 生成ルート
- `@/actions/App/Http/Controllers/Settings/ProfileController` → Wayfinder 生成アクション

## SFC の標準構造

```vue
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/login';
import type { SomeResource } from '@/types';

interface Props {
    items: SomeResource[];
    title: string;
}

const props = defineProps<Props>();

const filtered = computed(() => props.items.filter(i => i.active));
</script>

<template>
    <Head :title="title" />
    <Form v-bind="store.form()" v-slot="{ errors, processing }">
        <!-- ... -->
    </Form>
</template>
```

### defineOptions（レイアウト・パンくず）

```ts
defineOptions({
    layout: {
        title: 'Page title',
        description: 'Page description',
        breadcrumbs: [{ title: 'Settings', href: edit() }],
    },
});
```

`app.ts` の `layout` 関数がページ名に応じて `AppLayout` / `AuthLayout` / `SettingsLayout` を自動割当する。

### defineProps

- `interface Props` を明示定義し、`defineProps<Props>()` で使う
- デフォルト値が必要な場合は `withDefaults(defineProps<Props>(), { ... })`
- 他モジュールの型は `@/types/*` から `import type` で取得

### defineEmits

型付き形式を使用する:

```ts
const emit = defineEmits<{
    (e: 'clickSetting', item: Item): void
    (e: 'update:modelValue', value: string): void
}>();
```

### defineModel

`v-model` バインディングには `defineModel` を使う:

```ts
const modelValue = defineModel<Item>({ required: true })      // 単一 v-model
const startDate = defineModel<string>('startDate')            // 名前付き v-model
```

### defineExpose（モーダル等の公開 API）

```ts
const modalRef = useTemplateRef<InstanceType<typeof ModalDialog> | null>('modal')

function show(data: SomeData) {
    // 初期化処理
    modalRef.value?.show()
}

defineExpose({ show })
```

親からは `useTemplateRef` で参照し、`childRef.value?.show(data)` と呼ぶ。

## データフロー

```
Laravel Controller
  → Inertia::render('settings/Profile', [...props])
    → pages/settings/Profile.vue (defineProps / usePage で受け取り)
      → components/ (props / emits で子に渡す)
```

- **状態管理**: Inertia の props + `usePage()` をデータソースとする（Pinia 未使用）
- **フォーム送信**: Inertia `<Form>` コンポーネント + Wayfinder の `*.form()` を標準とする
  - 例: `<Form v-bind="store.form()" v-slot="{ errors, processing }">`
  - 例: `<Form v-bind="ProfileController.update.form()">`
- **ナビゲーション**: Wayfinder 生成の `@/routes/*` 関数（`edit()`, `request()` 等）
- **AJAX**: 現時点では Inertia フォーム + `router.visit()` が中心（Passkey 等）

## テストパターン（Vitest — 未導入・追加時の参考）

本プロジェクトには現時点で Vitest が未設定。導入時は以下パターンに従う。

### defineExpose した API のテスト（@vue/test-utils）

```ts
import { mount } from '@vue/test-utils'

it('初期表示', async () => {
    const wrapper = mount(TargetComponent)
    wrapper.vm.show(testData)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[data-testid="target"]').exists()).toBe(true)
})
```

### ユーザー操作テスト（@testing-library/vue）

```ts
import { render, fireEvent } from '@testing-library/vue'

it('ボタンクリックで送信される', async () => {
    const { getByRole } = render(TargetComponent, { props: testProps })
    await fireEvent.click(getByRole('button'))
})
```

### Inertia router / Wayfinder のモック

```ts
import { router } from '@inertiajs/vue3'
vi.mock('@inertiajs/vue3')
vi.spyOn(router, 'post')   // アサーションは URL のみに絞る
```

- props はモジュールスコープの定数オブジェクトとして定義する
- `fireEvent` / `nextTick` には必ず `await` を付ける
- モックは `mockClear()` / `mockReset()` でテスト間の漏れを防ぐ

## アンチパターン（禁止事項）

| 禁止 | 正しい方法 |
|------|------------|
| Options API / `defineComponent()` | `<script setup lang="ts">` |
| `any` 型 | `unknown` + 型ガード |
| 値と型の混在 import | `import type { ... }` で分離 |
| コンポーネント間の `provide/inject` 乱用 | props / emits で受け渡す |
| `<script>` と `<script setup>` の併用 | `<script setup lang="ts">` のみ |
| 共通UIコンポーネント（`components/ui/`）の内部改変 | ラッパーコンポーネント or ページ側で対応 |
| 直書き hex 色・インライン style | Tailwind ユーティリティ（`bg-background` 等） |
| Wayfinder 生成ファイル（`routes/`, `actions/`）の手動編集 | Controller / Route 変更後に再生成 |

## プロジェクト固有の注意

- Inertia ページ名は `pages/` 配下のパスと一致（例: `pages/auth/Login.vue` → `'auth/Login'`）
- スタイリングは SCSS ではなく Tailwind CSS v4 + CSS カスタムプロパティ（`design-consistency.mdc` 参照）
- `composables/` は既存パターン（`useAppearance`, `useTwoFactorAuth` 等）に合わせて追加する
