<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Modules\Identity\Domain\Enums\ProfileStatus;

/**
 * @property int $id
 * @property int $app_user_id
 * @property int $channel_id
 * @property int $activity_type_id
 * @property ProfileStatus $status
 * @property string|null $note
 */
class RepProfile extends Model
{
    use BelongsToChannel;

    /**
     * This table names its channel `channel_id`, not `supply_channel_id`.
     *
     * Relaxed: read by RegisterRep and by every RepDirectory lookup, both keyed on
     * `app_user_id` and both called before the caller belongs to a channel. During
     * registration the rep is choosing a channel, so there is nothing to scope by yet.
     *
     * Relaxed is not unscoped: with a tenant set the filter applies in full.
     */
    protected string $channelColumn = 'channel_id';

    protected bool $channelScopeOptional = true;

    protected $fillable = [
        'app_user_id',
        'channel_id',
        'activity_type_id',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProfileStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'app_user_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(RepProfileZone::class);
    }

    /**
     * @return list<int>
     */
    public function zoneIds(): array
    {
        return $this->zones()->pluck('zone_id')->map(fn ($id) => (int) $id)->all();
    }
}
