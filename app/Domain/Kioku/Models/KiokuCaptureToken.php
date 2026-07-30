<?php

namespace App\Domain\Kioku\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $token_hash
 * @property string $token_prefix
 * @property string $scope
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 */
class KiokuCaptureToken extends Model
{
    use BelongsToUser, HasUlids;

    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'token_prefix',
        'scope',
        'last_used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->scope === 'kioku:capture';
    }
}
