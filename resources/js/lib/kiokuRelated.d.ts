declare module '@/lib/kiokuRelated.mjs' {
    export function sharedMemoryTags(
        left: string[] | null | undefined,
        right: string[] | null | undefined,
    ): string[];

    export function relatedMemoryReason(
        memory: { tags?: string[] | null },
        related: { tags?: string[] | null },
    ): string;
}
