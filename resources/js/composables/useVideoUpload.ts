import { ref } from 'vue';
import { ApiError, apiFetch } from '@/lib/apiFetch';

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

            Object.entries(upload.headers).forEach(([key, value]) => {
                xhr?.setRequestHeader(key, value);
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
                mime_type: file.type,
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

        if (body.errors?.upload?.[0]) {
            return body.errors.upload[0];
        }

        if (body.message) {
            return body.message;
        }
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'アップロードに失敗しました。';
}
