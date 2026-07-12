<?php

namespace App\Domain\Kioku\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $source_type
 * @property string|null $external_account_id
 * @property string|null $external_account_email
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property list<string>|null $scopes
 * @property string $status
 * @property int $connection_version
 * @property Carbon|null $last_sync_attempt_at
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error_code
 * @property Carbon|null $last_error_at
 */
#[Fillable([
    'user_id',
    'source_type',
    'external_account_id',
    'external_account_email',
    'access_token',
    'refresh_token',
    'token_expires_at',
    'scopes',
    'status',
    'connection_version',
    'last_sync_attempt_at',
    'last_synced_at',
    'last_error_code',
    'last_error_at',
])]
class Connector extends Model
{
    use BelongsToUser, HasUlids;

    public const SOURCE_GOOGLE_CALENDAR = 'google_calendar';

    /**
     * Tokens must never leak through serialization or debug output.
     *
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'idle',
        'connection_version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'connection_version' => 'integer',
            'last_sync_attempt_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    /**
     * Next generation for OAuth connect / account switch / reconnect.
     * Disconnect does not bump; in-flight jobs for the old generation become no-ops.
     */
    public function nextConnectionVersion(): int
    {
        return ((int) $this->connection_version) + 1;
    }
}
