<?php

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reference\Database\Factories\GovernorateFactory;

/**
 * @property int $id
 * @property string $name_ar
 * @property string $name_en
 * @property string $code
 */
class Governorate extends Model
{
    /** @use HasFactory<GovernorateFactory> */
    use HasFactory;

    protected $fillable = [
        'name_ar',
        'name_en',
        'code',
    ];

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    protected static function newFactory(): GovernorateFactory
    {
        return GovernorateFactory::new();
    }
}
