declare module '@/lib/kiokuTranscriptDisplay.mjs' {
    export function kiokuTranscriptDisplayMode(input: {
        transcriptionEnabled: boolean;
        transcriptionStatus: string | null;
        transcriptText: string | null;
    }):
        | 'text'
        | 'empty_ready'
        | 'not_configured'
        | 'failed'
        | 'processing';
}
