<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Enums\OfferType;
use Modules\Promotion\Domain\Enums\TargetingScope;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property string $name
 * @property OfferType $type
 * @property string|null $description
 * @property OfferStatus $status
 * @property bool $stackable
 * @property int $priority
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $total_qty
 * @property int|null $per_retailer_max
 * @property int|null $per_order_max
 * @property int $min_invoice_value
 * @property int|null $min_items
 * @property TargetingScope $targeting_scope
 * @property array<string, mixed>|null $rules
 * @property string|null $stop_reason
 * @property-read OfferRedemption|null $redemption
 * @property-read Collection<int, OfferComponent> $components
 * @property-read Collection<int, OfferReward> $rewards
 * @property-read Collection<int, OfferGroup> $groups
 * @property-read Collection<int, OfferZone> $zones
 * @property-read Collection<int, OfferRetailer> $retailers
 * @property-read Collection<int, OfferActivityType> $activityTypes
 * @property-read Collection<int, OfferMedia> $media
 */
class Offer extends Model
{
    use BelongsToChannel;

    protected $guarded = ['status'];

    protected $fillable = [
        'supply_channel_id',
        'name',
        'type',
        'description',
        'stackable',
        'priority',
        'starts_at',
        'ends_at',
        'total_qty',
        'per_retailer_max',
        'per_order_max',
        'min_invoice_value',
        'min_items',
        'targeting_scope',
        'rules',
        'stop_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => OfferType::class,
            'status' => OfferStatus::class,
            'targeting_scope' => TargetingScope::class,
            'stackable' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'rules' => 'array',
            'min_invoice_value' => 'integer',
        ];
    }

    public function media(): HasMany
    {
        return $this->hasMany(OfferMedia::class)->orderBy('order');
    }

    public function components(): HasMany
    {
        return $this->hasMany(OfferComponent::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(OfferReward::class);
    }

    public function activityTypes(): HasMany
    {
        return $this->hasMany(OfferActivityType::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(OfferZone::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(OfferGroup::class);
    }

    public function retailers(): HasMany
    {
        return $this->hasMany(OfferRetailer::class);
    }

    public function redemption(): HasOne
    {
        return $this->hasOne(OfferRedemption::class);
    }
}
