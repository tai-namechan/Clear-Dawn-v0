<?php

namespace App\Domain\Kioku\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KiokuRecallFeedback extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'kioku_recall_feedback';

    protected $fillable = [
        'user_id',
        'search_session_id',
        'query_hash',
        'memory_id',
        'shown_rank',
        'tag_rank',
        'fulltext_rank',
        'vector_rank',
        'final_score',
        'verdict',
    ];

    protected function casts(): array
    {
        return [
            'shown_rank' => 'integer',
            'tag_rank' => 'integer',
            'fulltext_rank' => 'integer',
            'vector_rank' => 'integer',
            'final_score' => 'float',
        ];
    }

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }
}
