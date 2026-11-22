import { isNativeApp } from '@/lib/native/platform';
import {
    isUserCancellation,
    normalizeScannedCode,
} from '@/lib/nativeMedia.mjs';

/**
 * Opens the iOS barcode / QR reader. Returns the code, or null when the
 * user cancels. Only call this when `isNativeApp()` is true.
 *
 * The plugin is imported on demand: its web fallback pulls in a large
 * scanner library that browser users never need.
 */
export async function scanNativeBarcode(): Promise<string | null> {
    if (!isNativeApp()) {
        return null;
    }

    const { CapacitorBarcodeScanner, CapacitorBarcodeScannerTypeHint } =
        await import('@capacitor/barcode-scanner');

    try {
        const result = await CapacitorBarcodeScanner.scanBarcode({
            hint: CapacitorBarcodeScannerTypeHint.ALL,
            scanInstructions: 'バーコードを枠の中に合わせてください',
            cancelButtonAccessibilityLabel: 'キャンセル',
        });

        return normalizeScannedCode(result.ScanResult);
    } catch (error) {
        if (isUserCancellation(error)) {
            return null;
        }

        throw error;
    }
}
