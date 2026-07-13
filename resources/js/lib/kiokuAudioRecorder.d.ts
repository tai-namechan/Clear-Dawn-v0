declare module '@/lib/kiokuAudioRecorder.mjs' {
    export const KIOKU_MAX_RECORDING_MS: number;
    export const PREFERRED_AUDIO_MIME_TYPES: string[];

    export function pickSupportedAudioMimeType(
        isTypeSupported: (mimeType: string) => boolean,
    ): string | null;

    export function shouldAutoStopRecording(
        elapsedMs: number,
        maxMs?: number,
    ): boolean;

    export function formatRecordingElapsed(elapsedMs: number): string;

    export function fileExtensionForAudioMime(
        mimeType: string | null | undefined,
    ): string;
}
