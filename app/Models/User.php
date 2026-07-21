<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string|null $timezone
 * @property int $memory_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'timezone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<LifeArea, $this>
     */
    public function lifeAreas(): HasMany
    {
        return $this->hasMany(LifeArea::class);
    }

    /**
     * @return HasMany<MatrixCell, $this>
     */
    public function matrixCells(): HasMany
    {
        return $this->hasMany(MatrixCell::class);
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * @return HasMany<Video, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    /**
     * @return HasMany<RoutineItem, $this>
     */
    public function routineItems(): HasMany
    {
        return $this->hasMany(RoutineItem::class);
    }

    /**
     * @return HasMany<Routine, $this>
     */
    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }

    /**
     * @return HasMany<RoutinePlan, $this>
     */
    public function routinePlans(): HasMany
    {
        return $this->hasMany(RoutinePlan::class);
    }

    /**
     * @return HasMany<RoutineSession, $this>
     */
    public function routineSessions(): HasMany
    {
        return $this->hasMany(RoutineSession::class);
    }

    /**
     * @return HasMany<MetricRecord, $this>
     */
    public function metricRecords(): HasMany
    {
        return $this->hasMany(MetricRecord::class);
    }

    /**
     * @return HasMany<FoodItem, $this>
     */
    public function foodItems(): HasMany
    {
        return $this->hasMany(FoodItem::class);
    }

    /**
     * @return HasMany<MealEntry, $this>
     */
    public function mealEntries(): HasMany
    {
        return $this->hasMany(MealEntry::class);
    }

    /**
     * @return HasMany<NutritionGoal, $this>
     */
    public function nutritionGoals(): HasMany
    {
        return $this->hasMany(NutritionGoal::class);
    }

    /**
     * @return HasMany<Goal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /**
     * @return HasMany<PersonalProfileEntry, $this>
     */
    public function personalProfileEntries(): HasMany
    {
        return $this->hasMany(PersonalProfileEntry::class);
    }

    /**
     * @return HasMany<DailyCheckin, $this>
     */
    public function dailyCheckins(): HasMany
    {
        return $this->hasMany(DailyCheckin::class);
    }

    /**
     * @return HasMany<SymptomObservation, $this>
     */
    public function symptomObservations(): HasMany
    {
        return $this->hasMany(SymptomObservation::class);
    }

    /**
     * @return HasMany<DailyResourceState, $this>
     */
    public function dailyResourceStates(): HasMany
    {
        return $this->hasMany(DailyResourceState::class);
    }

    /**
     * @return HasMany<Recommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    /**
     * @return HasMany<RuleDefinition, $this>
     */
    public function ruleDefinitions(): HasMany
    {
        return $this->hasMany(RuleDefinition::class);
    }

    /**
     * @return HasMany<NutritionTargetProfile, $this>
     */
    public function nutritionTargetProfiles(): HasMany
    {
        return $this->hasMany(NutritionTargetProfile::class);
    }

    /**
     * @return HasMany<UserModuleSetting, $this>
     */
    public function moduleSettings(): HasMany
    {
        return $this->hasMany(UserModuleSetting::class);
    }
}
