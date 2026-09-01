<?php

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reference\Database\Factories\ZoneFactory;
use Modules\Reference\Domain\Enums\ZoneStatus;

/**
 * @property int $id
 * @property int $governorate_id
 * @property string $name
 * @property array<string, mixed>|null $polygon
 * @property ZoneStatus $status
 */
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'governorate_id',
        'name',
        'polygon',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'polygon' => 'array',
            'status' => ZoneStatus::class,
        ];
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function channelZones(): HasMany
    {
        return $this->hasMany(ChannelZone::class);
    }

    protected static function newFactory(): ZoneFactory
    {
        return ZoneFactory::new();
    }
}
