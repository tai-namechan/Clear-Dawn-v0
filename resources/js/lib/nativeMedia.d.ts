export function nativePhotoFileName(
    mimeType: string,
    takenAt: Date,
    prefix?: string,
): string;
export function normalizeScannedCode(
    raw: string | null | undefined,
): string | null;
export function isUserCancellation(error: unknown): boolean;
