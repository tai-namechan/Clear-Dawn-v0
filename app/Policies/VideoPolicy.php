<?php

namespace App\Policies;

use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    public function view(User $user, Video $video): bool
    {
        return $this->owns($user, $video) && $video->status === VideoStatus::Ready;
    }

    public function update(User $user, Video $video): bool
    {
        return $this->owns($user, $video);
    }

    public function delete(User $user, Video $video): bool
    {
        return $this->owns($user, $video);
    }

    public function finalize(User $user, Video $video): bool
    {
        if (! $this->owns($user, $video)) {
            return false;
        }

        return in_array($video->status, [VideoStatus::Pending, VideoStatus::Ready], true);
    }

    public function refreshUploadUrl(User $user, Video $video): bool
    {
        return $this->owns($user, $video) && $video->status === VideoStatus::Pending;
    }

    public function streamUrl(User $user, Video $video): bool
    {
        return $this->owns($user, $video) && $video->status === VideoStatus::Ready;
    }

    private function owns(User $user, Video $video): bool
    {
        return $video->user_id === $user->id;
    }
}
