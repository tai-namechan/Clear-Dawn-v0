<?php

namespace App\Domain\Kioku\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class KiokuActionExport extends Model
{
    use BelongsToUser, HasUlids;

    protected $fillable = [
        'user_id',
        'memory_id',
        'target',
        'target_id',
    ];
}
