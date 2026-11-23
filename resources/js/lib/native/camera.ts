import { Camera } from '@capacitor/camera';
import { isNativeApp } from '@/lib/native/platform';
import { isUserCancellation, nativePhotoFileName } from '@/lib/nativeMedia.mjs';

/**
 * Takes a photo with the iOS camera and returns it as a File, so the
 * existing upload code can use it unchanged. Returns null when the user
 * cancels. Only call this when `isNativeApp()` is true.
 */
export async function takeNativePhoto(prefix = 'photo'): Promise<File | null> {
    if (!isNativeApp()) {
        return null;
    }

    try {
        const result = await Camera.takePhoto({
            quality: 85,
            targetWidth: 2048,
            correctOrientation: true,
        });

        if (!result.webPath) {
            return null;
        }

        const blob = await (await fetch(result.webPath)).blob();
        const type = blob.type || 'image/jpeg';

        return new File([blob], nativePhotoFileName(type, new Date(), prefix), {
            type,
        });
    } catch (error) {
        if (isUserCancellation(error)) {
            return null;
        }

        throw error;
    }
}
