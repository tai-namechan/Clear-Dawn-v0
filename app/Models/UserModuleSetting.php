<?php

namespace App\Models;

use App\Enums\ModuleKey;
use Database\Factories\UserModuleSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * モジュール有効/無効（ADR-0011。未登録の module_key は有効とみなす）。
 *
 * @property string $id
 * @property int $user_id
 * @property ModuleKey $module_key
 * @property bool $is_enabled
 */
#[Fillable(['user_id', 'module_key', 'is_enabled'])]
class UserModuleSetting extends Model
{
    /** @use HasFactory<UserModuleSettingFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module_key' => ModuleKey::class,
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function isEnabledFor(User $user, ModuleKey $module): bool
    {
        $setting = self::query()
            ->where('user_id', $user->id)
            ->where('module_key', $module->value)
            ->first();

        return $setting === null || $setting->is_enabled;
    }
}
