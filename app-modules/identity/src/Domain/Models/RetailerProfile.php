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
 * @property string $shop_name
 * @property int $activity_type_id
 * @property int $governorate_id
 * @property int $zone_id
 * @property float|null $lat
 * @property float|null $lng
 * @property string|null $address
 * @property ProfileStatus $status
 */
class RetailerProfile extends Model
{
    protected $fillable = [
        'app_user_id',
        'shop_name',
        'activity_type_id',
        'governorate_id',
        'zone_id',
        'lat',
        'lng',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProfileStatus::class,
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'app_user_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(RetailerProfileCategory::class);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(RetailerProfileEquipment::class);
    }
}
