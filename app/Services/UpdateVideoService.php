<?php

namespace App\Services;

use App\Models\Video;

class UpdateVideoService
{
    /**
     * @param  array{title?: string, description?: string|null, life_area_id?: string|null, routine_item_id?: string|null}  $attributes
     */
    public function handle(Video $video, array $attributes): Video
    {
        $video->update($attributes);

        return $video->refresh();
    }
}
