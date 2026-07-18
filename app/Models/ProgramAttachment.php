<?php

namespace App\Models;

use Database\Factories\ProgramAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * プログラムの添付資料（PDF 等の設計根拠。参照専用）。
 *
 * @property string $id
 * @property int $user_id
 * @property string $program_version_id
 * @property string $title
 * @property string $disk
 * @property string $path
 * @property string|null $mime_type
 * @property int|null $byte_size
 */
#[Fillable([
    'user_id',
    'program_version_id',
    'title',
    'disk',
    'path',
    'mime_type',
    'byte_size',
])]
class ProgramAttachment extends Model
{
    /** @use HasFactory<ProgramAttachmentFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ProgramVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }
}
