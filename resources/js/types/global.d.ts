import type { Auth } from '@/types/auth';
import type { ProductDefinition, ProductKey } from '@/types/product';

declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentProduct: ProductKey;
            products: ProductDefinition[];
            aiUsageBanner: {
                warning: boolean;
                at_limit: boolean;
                progress_ratio: string;
                remaining_usd: string;
                limit_usd: string;
                spent_usd: string;
                reserved_usd: string;
            } | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
