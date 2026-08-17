<?php

namespace App\Services;

use App\Models\FoodLookupRequest;
use App\Models\MealEntry;
use Illuminate\Support\Facades\Storage;

/**
 * 食事記録に紐づく料理写真の永続化・複製・削除。
 * 解析用の一時画像（food_lookup_requests.temp_image_path）を、
 * 確定時に meal-photos/{userId}/{entryId}.ext へ移す。
 *
 * 表示用に残すのは料理写真アップロード（food-photo-estimate/）だけ。
 * 栄養値の出典（ai_photo_estimate / nutrition_db）は問わない。
 * 成分表 OCR の画像は数値を読むための素材なので、確定時に破棄する。
 * 写真は meal_entries にだけ置き、food_items / バーコードカタログには載せない。
 * food_items.source は後から上書きされるので、確定済み写真の削除判定には使わない。
 */
class MealPhotoService
{
    /**
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const DISPLAY_PHOTO_PREFIX = 'food-photo-estimate/';

    public function attachFromLookup(MealEntry $entry, FoodLookupRequest $lookup): void
    {
        if (! $this->shouldPersistLookupPhoto($lookup)) {
            $this->discardLookupTemp($lookup);

            return;
        }

        if ($entry->photo_path !== null) {
            $this->discardLookupTemp($lookup);

            return;
        }

        $sourcePath = $lookup->temp_image_path;
        if ($sourcePath === null) {
            return;
        }

        $disk = Storage::disk($this->disk());
        if (! $disk->exists($sourcePath)) {
            $this->discardLookupTemp($lookup);

            return;
        }

        $destination = $this->pathFor($entry, $sourcePath);
        $disk->copy($sourcePath, $destination);

        $entry->forceFill(['photo_path' => $destination])->save();
        $this->discardLookupTemp($lookup);
    }

    public function copyTo(MealEntry $source, MealEntry $destination): void
    {
        if ($source->photo_path === null || $destination->photo_path !== null) {
            return;
        }

        $disk = Storage::disk($this->disk());
        if (! $disk->exists($source->photo_path)) {
            return;
        }

        $destinationPath = $this->pathFor($destination, $source->photo_path);
        $disk->copy($source->photo_path, $destinationPath);
        $destination->forceFill(['photo_path' => $destinationPath])->save();
    }

    public function deleteFor(MealEntry $entry): void
    {
        if ($entry->photo_path === null) {
            return;
        }

        Storage::disk($this->disk())->delete($entry->photo_path);
    }

    public function exists(MealEntry $entry): bool
    {
        return $entry->photo_path !== null
            && $this->isOwnedPath($entry, $entry->photo_path)
            && Storage::disk($this->disk())->exists($entry->photo_path);
    }

    public function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    public function downloadName(MealEntry $entry): string
    {
        $extension = $this->safeExtension((string) $entry->photo_path);

        return $entry->eaten_on->toDateString().'_'.$entry->meal_type->value.'_'.$entry->id.'.'.$extension;
    }

    public function disk(): string
    {
        return (string) config('meals.label_ocr.disk', 'local');
    }

    private function shouldPersistLookupPhoto(FoodLookupRequest $lookup): bool
    {
        $path = $lookup->temp_image_path;
        if ($path === null || $path === '' || str_contains($path, '..')) {
            return false;
        }

        return str_starts_with($path, self::DISPLAY_PHOTO_PREFIX);
    }

    private function pathFor(MealEntry $entry, string $sourcePath): string
    {
        return 'meal-photos/'.$entry->user_id.'/'.$entry->id.'.'.$this->safeExtension($sourcePath);
    }

    private function safeExtension(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return 'jpg';
        }

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    private function isOwnedPath(MealEntry $entry, string $path): bool
    {
        $prefix = 'meal-photos/'.$entry->user_id.'/';

        return str_starts_with($path, $prefix) && ! str_contains($path, '..');
    }

    private function discardLookupTemp(FoodLookupRequest $lookup): void
    {
        $path = $lookup->temp_image_path;
        if ($path === null) {
            return;
        }

        Storage::disk($this->disk())->delete($path);
        $lookup->forceFill(['temp_image_path' => null])->save();
    }
}
