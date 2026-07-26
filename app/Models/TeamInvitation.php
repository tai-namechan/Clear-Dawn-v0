<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TeamInvitation extends Model
{
    use HasUlids;

    protected $fillable = ['team_id', 'email', 'target_user_id', 'role', 'invitee_type', 'token_hash', 'invited_by_team_user_id', 'expires_at', 'accepted_at', 'accepted_member_type', 'accepted_member_id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }
}
