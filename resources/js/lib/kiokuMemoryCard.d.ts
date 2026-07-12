declare module '@/lib/kiokuMemoryCard.mjs' {
    export function isKiokuMemoryCardNavigable(memory: {
        source_type: string;
        status: string;
    }): boolean;

    export function isKiokuMemoryCardEnriching(status: string): boolean;
}
