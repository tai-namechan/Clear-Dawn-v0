export function resolveVideoMimeType(file: {
    type?: string;
    name?: string;
}): string;

export function isMovVideoFile(
    file: { type?: string; name?: string } | null | undefined,
): boolean;
