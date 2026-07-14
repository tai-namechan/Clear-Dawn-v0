declare module '@/lib/kiokuMemoryCard.mjs' {
    export function isKiokuMemoryCardNavigable(memory: {
        source_type: string;
        status: string;
    }): boolean;

    export function isKiokuMemoryCardEnriching(
        memory: {
            source_type: string;
            status: string;
            transcription_status?: string | null;
        },
        options?: { transcriptionEnabled?: boolean },
    ): boolean;

    export function kiokuMemoryDisplayTitle(memory: {
        source_type: string;
        title: string;
        transcription_status?: string | null;
    }): string;
}
