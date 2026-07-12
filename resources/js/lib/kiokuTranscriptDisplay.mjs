/**
 * Detail page transcript panel mode. Separates ready-empty from "processing"
 * so an empty successful transcription is never shown as "文字起こし中".
 *
 * @param {{
 *   transcriptionEnabled: boolean,
 *   transcriptionStatus: string | null,
 *   transcriptText: string | null,
 * }} input
 * @returns {'text' | 'empty_ready' | 'not_configured' | 'failed' | 'processing'}
 */
export function kiokuTranscriptDisplayMode(input) {
    const text = input.transcriptText;

    if (text !== null && text !== '') {
        return 'text';
    }

    if (input.transcriptionStatus === 'ready') {
        return 'empty_ready';
    }

    if (!input.transcriptionEnabled) {
        return 'not_configured';
    }

    if (input.transcriptionStatus === 'failed') {
        return 'failed';
    }

    return 'processing';
}
