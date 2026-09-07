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

    /**
     * `status` is absent under rule 8, as on Governorate. It moves only through
     * EP-AD-034, which demands a reason and reports what the change affects. Leaving it
     * fillable would let PUT /zones/{id} disable a zone as a side effect of a rename,
     * with no reason recorded and no impact shown — and EP-AD-042B's body does not carry
     * `status` in the first place.
     */
    protected $fillable = [
        'governorate_id',
        'name',
        'district',
        'polygon',
        'order',
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
