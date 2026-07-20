import { onUnmounted, ref } from 'vue';
import type { Ref } from 'vue';

declare global {
    interface Window {
        BarcodeDetector?: new (options: { formats: string[] }) => {
            detect: (
                source: ImageBitmapSource,
            ) => Promise<{ rawValue: string }[]>;
        };
    }
}

interface UseBarcodeScan {
    isSupported: Ref<boolean>;
    scanning: Ref<boolean>;
    error: Ref<string | null>;
    videoRef: Ref<HTMLVideoElement | null>;
    start: () => Promise<void>;
    stop: () => void;
}

/**
 * ネイティブ BarcodeDetector を優先し、非対応ブラウザでのみ @zxing/browser を
 * 動的 import してフォールバックする（設計 §13.1）。zxing 一式はメインバンドルに含めない。
 */
export function useBarcodeScan(
    onDetected: (barcode: string) => void,
): UseBarcodeScan {
    const hasNativeDetector = 'BarcodeDetector' in window;
    const hasMediaDevices =
        typeof navigator !== 'undefined' &&
        Boolean(navigator.mediaDevices?.getUserMedia);
    const isSupported = ref(hasNativeDetector || hasMediaDevices);
    const scanning = ref(false);
    const error = ref<string | null>(null);
    const videoRef = ref<HTMLVideoElement | null>(null);

    let stream: MediaStream | null = null;
    let animationId: number | null = null;
    let zxingControls: { stop: () => void } | null = null;
    let lastDetectedCode = '';
    let lastDetectedAt = 0;

    function reportDetection(code: string): void {
        const now = Date.now();

        if (code !== lastDetectedCode || now - lastDetectedAt > 3000) {
            lastDetectedCode = code;
            lastDetectedAt = now;
            onDetected(code);
        }
    }

    async function start(): Promise<void> {
        if (!hasMediaDevices || !videoRef.value) {
            error.value =
                'カメラスキャンに対応していないブラウザです。バーコード番号を手動入力してください。';

            return;
        }

        error.value = null;

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
            });
            videoRef.value.srcObject = stream;
            await videoRef.value.play();
            scanning.value = true;

            if (hasNativeDetector) {
                detectLoopNative();
            } else {
                await startZxingFallback();
            }
        } catch {
            error.value =
                'カメラへのアクセスが拒否されました。設定から許可してください。';
        }
    }

    function stop(): void {
        scanning.value = false;

        if (animationId !== null) {
            cancelAnimationFrame(animationId);
            animationId = null;
        }

        if (zxingControls) {
            zxingControls.stop();
            zxingControls = null;
        }

        if (stream) {
            for (const track of stream.getTracks()) {
                track.stop();
            }

            stream = null;
        }

        if (videoRef.value) {
            videoRef.value.srcObject = null;
        }
    }

    function detectLoopNative(): void {
        if (!scanning.value || !videoRef.value || !window.BarcodeDetector) {
            return;
        }

        const detector = new window.BarcodeDetector({
            formats: ['ean_8', 'ean_13', 'upc_a'],
        });

        const tick = async (): Promise<void> => {
            if (!scanning.value || !videoRef.value) {
                return;
            }

            try {
                const barcodes = await detector.detect(videoRef.value);

                if (barcodes.length > 0) {
                    reportDetection(barcodes[0].rawValue);
                }
            } catch {
                // detection errors are transient
            }

            animationId = requestAnimationFrame(() => void tick());
        };

        animationId = requestAnimationFrame(() => void tick());
    }

    async function startZxingFallback(): Promise<void> {
        try {
            const [
                { BrowserMultiFormatOneDReader },
                { BarcodeFormat, DecodeHintType },
            ] = await Promise.all([
                import('@zxing/browser'),
                import('@zxing/library'),
            ]);

            // 動的 import 待ち中に stop() が呼ばれていたら何もしない
            if (!scanning.value || !videoRef.value || !stream) {
                return;
            }

            const hints = new Map();
            hints.set(DecodeHintType.POSSIBLE_FORMATS, [
                BarcodeFormat.EAN_8,
                BarcodeFormat.EAN_13,
                BarcodeFormat.UPC_A,
            ]);

            const reader = new BrowserMultiFormatOneDReader(hints);

            zxingControls = await reader.decodeFromStream(
                stream,
                videoRef.value,
                (result) => {
                    if (result) {
                        reportDetection(result.getText());
                    }
                },
            );
        } catch {
            error.value =
                'バーコード読み取り機能の読み込みに失敗しました。番号を手動入力してください。';
            stop();
        }
    }

    onUnmounted(stop);

    return { isSupported, scanning, error, videoRef, start, stop };
}
