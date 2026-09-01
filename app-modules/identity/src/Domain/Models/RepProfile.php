<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
