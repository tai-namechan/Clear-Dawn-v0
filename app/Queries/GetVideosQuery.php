<?php

namespace App\Queries;

use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetVideosQuery
{
    /**
     * @return LengthAwarePaginator<int, Video>
     */
    public function handle(User $user, int $perPage = 24): LengthAwarePaginator
    {
        return Video::query()
            ->where('user_id', $user->id)
            ->where('status', VideoStatus::Ready)
            ->with('lifeArea')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
