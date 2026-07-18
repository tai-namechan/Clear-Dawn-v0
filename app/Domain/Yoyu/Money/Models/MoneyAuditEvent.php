<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'actor_user_id',
    'event_type',
    'subject_type',
    'subject_id',
    'before_payload',
    'after_payload',
    'correlation_id',
    'occurred_at',
])]
class MoneyAuditEvent extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_audit_events';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_payload' => 'array',
            'after_payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
