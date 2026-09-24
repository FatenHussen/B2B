<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Enums\PlanOnExceed;
use Modules\Tenancy\Domain\Enums\PlanStatus;

/**
 * Subscription plan for supply channels (PA-02 / EP-AD-100A–D).
 *
 * Platform reference data — no channel scope. `status` is guarded (rule 8) and
 * changes only through PlanLifecycle.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property int $price_monthly
 * @property int $price_yearly
 * @property int|null $currency_id
 * @property array<string, int> $limits
 * @property list<string>|null $features
 * @property PlanOnExceed $on_exceed
 * @property int $trial_days
 * @property bool $is_public
 * @property PlanStatus $status
 * @property bool $is_active
 */
class ChannelPlan extends Model
{
    protected $table = 'channel_plans';

    protected $fillable = [
        'key',
        'name',
        'price_monthly',
        'price_yearly',
        'currency_id',
        'limits',
        'features',
        'on_exceed',
        'trial_days',
        'is_public',
        'is_active',
    ];

    /**
     * Rule 8: status is never mass-assigned.
     *
     * @var list<string>
     */
    protected $guarded = ['status'];

    /**
     * The initial status is a definition, not a write: a new plan is `active` and
     * nothing outside PlanLifecycle assigns the column (ChannelStatusWriterTest).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'currency_id' => 'integer',
            'trial_days' => 'integer',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'on_exceed' => PlanOnExceed::class,
            'status' => PlanStatus::class,
        ];
    }
}
