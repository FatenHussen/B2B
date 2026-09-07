<?php

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reference\Database\Factories\GovernorateFactory;
use Modules\Reference\Domain\Enums\RefStatus;

/**
 * @property int $id
 * @property string $name_ar
 * @property string $name_en
 * @property string $code
 * @property RefStatus $status
 * @property int $order
 */
class Governorate extends Model
{
    /** @use HasFactory<GovernorateFactory> */
    use HasFactory;

    /**
     * `status` is absent on purpose. Rule 8 keeps it out of mass assignment: it moves
     * only through EP-AD-043A, which demands a reason and reports what the change
     * affects. Leaving it fillable would let PUT .../governorates/{id} disable a
     * governorate silently as a side effect of a rename.
     */
    protected $fillable = [
        'name_ar',
        'name_en',
        'code',
        'order',
    ];

    /**
     * The column defaults to 'active' in the database, which is not the same thing as the
     * attribute being set on a freshly created instance: `Governorate::create()` returns a
     * model whose `status` is still null until it is reloaded, and the resource then reads
     * `->value` on null and 500s. This makes the default the model's own.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => RefStatus::Active->value,
        'order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RefStatus::class,
            'order' => 'integer',
        ];
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    protected static function newFactory(): GovernorateFactory
    {
        return GovernorateFactory::new();
    }
}
