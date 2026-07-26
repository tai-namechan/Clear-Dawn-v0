<?php

namespace App\Models;

use Database\Factories\TeamUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['google_subject', 'email', 'name', 'avatar_url', 'status', 'last_authenticated_at'])]
class TeamUser extends Authenticatable
{
    /** @use HasFactory<TeamUserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return ['last_authenticated_at' => 'datetime'];
    }

    public function memberships(): MorphMany
    {
        return $this->morphMany(TeamMembership::class, 'member');
    }
}
