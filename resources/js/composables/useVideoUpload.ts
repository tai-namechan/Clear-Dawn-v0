import { ref } from 'vue';
import { ApiError, apiFetch } from '@/lib/apiFetch';
import { resolveVideoMimeType } from '@/lib/videoMimeType.mjs';

export type VideoUploadState =
    | 'idle'
    | 'preparing'
    | 'uploading'
    | 'finalizing'
    | 'done'
    | 'error'
    | 'cancelled';

export type VideoUploadProgress = {
    loaded: number;
    total: number;
    percent: number;
};

type UploadUrlResponse = {
    mode: string;
    video_id: string;
    uploads: Array<{
        url: string;
        headers: Record<string, string>;
        expires_at: string;
    }>;
};

type FinalizeResponse = {
    video?: {
        id: string;
        status: string;
    };
};

export type UseVideoUploadOptions = {
    maxRetries?: number;
    onSuccess?: (videoId: string) => void;
    onError?: (message: string) => void;
};

export function useVideoUpload(options: UseVideoUploadOptions = {}) {
    const maxRetries = options.maxRetries ?? 2;

    const state = ref<VideoUploadState>('idle');
    const progress = ref<VideoUploadProgress>({
        loaded: 0,
        total: 0,
        percent: 0,
    });
    const errorMessage = ref<string | null>(null);
    const videoId = ref<string | null>(null);

    let xhr: XMLHttpRequest | null = null;
    let cancelled = false;

    function reset(): void {
        state.value = 'idle';
        progress.value = { loaded: 0, total: 0, percent: 0 };
        errorMessage.value = null;
        videoId.value = null;
        cancelled = false;
        xhr = null;
    }

    function cancel(): void {
        cancelled = true;
        xhr?.abort();
        xhr = null;

        if (state.value === 'uploading' || state.value === 'preparing') {
            state.value = 'cancelled';
        }
    }

    async function requestUploadUrl(payload: {
        title: string;
        mime_type: string;
        size_bytes: number;
        duration_seconds?: number | null;
    }): Promise<UploadUrlResponse> {
        return apiFetch<UploadUrlResponse>('/videos/upload-url', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    }

    async function refreshUploadUrl(id: string): Promise<UploadUrlResponse> {
        return apiFetch<UploadUrlResponse>(`/videos/${id}/upload-url`, {
            method: 'POST',
        });
    }

    async function finalize(id: string): Promise<FinalizeResponse> {
        return apiFetch<FinalizeResponse>(`/videos/${id}/finalize`, {
            method: 'POST',
        });
    }

    function uploadFileToUrl(
        file: File,
        upload: UploadUrlResponse['uploads'][number],
    ): Promise<void> {
        return new Promise((resolve, reject) => {
            xhr = new XMLHttpRequest();
            xhr.open('PUT', upload.url, true);

            // Browsers forbid setting Host / Content-Length etc. on XHR.
            // Presigned S3/R2 responses often include Host in signed headers.
            const unsafeHeaders = new Set([
                'host',
                'content-length',
                'connection',
                'cookie',
                'origin',
                'referer',
                'transfer-encoding',
                'keep-alive',
                'te',
                'trailer',
                'upgrade',
                'via',
                'accept-encoding',
                'accept-charset',
            ]);

            Object.entries(upload.headers).forEach(([key, value]) => {
                const lower = key.toLowerCase();

                if (
                    unsafeHeaders.has(lower) ||
                    lower.startsWith('proxy-') ||
                    lower.startsWith('sec-')
                ) {
                    return;
                }

                const headerValue = Array.isArray(value)
                    ? value.join(', ')
                    : String(value);

                xhr?.setRequestHeader(key, headerValue);
            });

            xhr.upload.onprogress = (event) => {
                if (!event.lengthComputable) {
                    return;
                }

                progress.value = {
                    loaded: event.loaded,
                    total: event.total,
                    percent: Math.round((event.loaded / event.total) * 100),
                };
            };

            xhr.onload = () => {
                if (xhr && xhr.status >= 200 && xhr.status < 300) {
                    resolve();

                    return;
                }

                reject(
                    new Error(`Upload failed with status ${xhr?.status ?? 0}`),
                );
            };

            xhr.onerror = () => {
                reject(new Error('Upload network error'));
            };

            xhr.onabort = () => {
                reject(new Error('Upload cancelled'));
            };

            xhr.send(file);
        });
    }

    async function upload(file: File, title: string): Promise<string | null> {
        reset();
        cancelled = false;
        state.value = 'preparing';
        errorMessage.value = null;

        let attempt = 0;
        let uploadInfo: UploadUrlResponse | null = null;

        try {
            uploadInfo = await requestUploadUrl({
                title,
                mime_type: resolveVideoMimeType(file),
                size_bytes: file.size,
            });
            videoId.value = uploadInfo.video_id;
        } catch (error) {
            state.value = 'error';
            errorMessage.value = resolveErrorMessage(error);
            options.onError?.(errorMessage.value);

            return null;
        }

        while (attempt <= maxRetries) {
            if (cancelled) {
                return null;
            }

            try {
                state.value = 'uploading';
                progress.value = { loaded: 0, total: file.size, percent: 0 };

                const targetUpload = uploadInfo.uploads[0];

                if (!targetUpload) {
                    throw new Error('Upload URL missing');
                }

                await uploadFileToUrl(file, targetUpload);

                if (cancelled) {
                    return null;
                }

                state.value = 'finalizing';
                await finalize(uploadInfo.video_id);

                state.value = 'done';
                options.onSuccess?.(uploadInfo.video_id);

                return uploadInfo.video_id;
            } catch (error) {
                if (cancelled) {
                    return null;
                }

                attempt += 1;

                if (attempt > maxRetries) {
                    state.value = 'error';
                    errorMessage.value = resolveErrorMessage(error);
                    options.onError?.(errorMessage.value);

                    return null;
                }

                try {
                    uploadInfo = await refreshUploadUrl(uploadInfo.video_id);
                } catch (refreshError) {
                    state.value = 'error';
                    errorMessage.value = resolveErrorMessage(refreshError);
                    options.onError?.(errorMessage.value);

                    return null;
                }
            }
        }

        return null;
    }

    return {
        state,
        progress,
        errorMessage,
        videoId,
        upload,
        cancel,
        reset,
    };
}

function resolveErrorMessage(error: unknown): string {
    if (error instanceof ApiError) {
        const body = error.body as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        const fieldError =
            body.errors?.upload?.[0] ??
            body.errors?.mime_type?.[0] ??
            body.errors?.finalize?.[0] ??
            body.errors?.title?.[0] ??
            body.errors?.size_bytes?.[0];

        if (fieldError) {
            return fieldError;
        }

        if (body.message) {
            return body.message;
        }

        return 'アップロードに失敗しました。';
    }

    if (error instanceof Error) {
        // Safari often surfaces JSON parse failures with this English message.
        if (
            error.message === 'The string did not match the expected pattern.'
        ) {
            return 'アップロードに失敗しました。通信またはサーバー応答を確認してください。';
        }

        return error.message;
    }

    return 'アップロードに失敗しました。';
}
