import { onUnmounted, ref, type Ref } from 'vue';

declare global {
    interface Window {
        BarcodeDetector?: new (options: { formats: string[] }) => {
            detect: (source: ImageBitmapSource) => Promise<{ rawValue: string }[]>;
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

export function useBarcodeScan(
    onDetected: (barcode: string) => void,
): UseBarcodeScan {
    const isSupported = ref('BarcodeDetector' in window);
    const scanning = ref(false);
    const error = ref<string | null>(null);
    const videoRef = ref<HTMLVideoElement | null>(null);

    let stream: MediaStream | null = null;
    let animationId: number | null = null;
    let lastDetectedCode = '';
    let lastDetectedAt = 0;

    async function start(): Promise<void> {
        if (!isSupported.value || !videoRef.value) {
            error.value = 'カメラスキャンに対応していないブラウザです。バーコード番号を手動入力してください。';

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
            detectLoop();
        } catch {
            error.value = 'カメラへのアクセスが拒否されました。設定から許可してください。';
        }
    }

    function stop(): void {
        scanning.value = false;

        if (animationId !== null) {
            cancelAnimationFrame(animationId);
            animationId = null;
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

    function detectLoop(): void {
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
                    const code = barcodes[0].rawValue;
                    const now = Date.now();

                    if (code !== lastDetectedCode || now - lastDetectedAt > 3000) {
                        lastDetectedCode = code;
                        lastDetectedAt = now;
                        onDetected(code);
                    }
                }
            } catch {
                // detection errors are transient
            }

            animationId = requestAnimationFrame(() => void tick());
        };

        animationId = requestAnimationFrame(() => void tick());
    }

    onUnmounted(stop);

    return { isSupported, scanning, error, videoRef, start, stop };
}
