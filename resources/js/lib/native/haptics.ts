import { Haptics, ImpactStyle, NotificationType } from '@capacitor/haptics';
import { isNativeApp } from '@/lib/native/platform';

/**
 * Light tap feedback for record-type actions (docs/product/ios-app.md).
 * Never throws: feedback is a nicety, not part of the save.
 */
export async function hapticSuccess(): Promise<void> {
    if (!isNativeApp()) {
        return;
    }

    try {
        await Haptics.notification({ type: NotificationType.Success });
    } catch {
        // ignore
    }
}

export async function hapticTap(): Promise<void> {
    if (!isNativeApp()) {
        return;
    }

    try {
        await Haptics.impact({ style: ImpactStyle.Light });
    } catch {
        // ignore
    }
}
