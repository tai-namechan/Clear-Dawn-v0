/**
 * 成分表撮影用の画像選択 + クライアント縮小（PR-F2 設計 G）。
 * `<input type="file" accept="image/*" capture="environment">` で取得した画像を
 * canvas で長辺 1200px / JPEG q0.85 に再エンコードしてから upload する。
 * 縮小は AI 課金の予約額と転送量を抑えるためで、失敗時は原本をそのまま返し
 * サーバー側 validation（≦5MB / ≦8000px）に委ねる。
 */

const MAX_EDGE_PX = 1200;
const JPEG_QUALITY = 0.85;

export async function downscaleLabelImage(file: File): Promise<File> {
    try {
        const bitmap = await createImageBitmap(file);
        const { width, height } = bitmap;
        const longEdge = Math.max(width, height);

        if (longEdge <= MAX_EDGE_PX && file.type === 'image/jpeg') {
            bitmap.close();

            return file;
        }

        const scale = Math.min(1, MAX_EDGE_PX / longEdge);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);

        const context = canvas.getContext('2d');

        if (!context) {
            bitmap.close();

            return file;
        }

        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY),
        );

        if (!blob) {
            return file;
        }

        return new File([blob], 'label.jpg', { type: 'image/jpeg' });
    } catch {
        // HEIC 等 createImageBitmap 非対応の形式は原本のまま送る
        return file;
    }
}
