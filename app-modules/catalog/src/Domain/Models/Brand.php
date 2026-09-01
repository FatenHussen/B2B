<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Core\Support\Concerns\BelongsToChannel;

class Brand extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'name_ar',
        'name_en',
        'description',
        'logo_media_id',
        'banner_media_id',
        'order',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => BrandStatus::class];
    }

    public function activityTypes(): HasMany
    {
        return $this->hasMany(BrandActivityType::class);
    }

    public function sliders(): HasMany
    {
        return $this->hasMany(BrandSlider::class);
    }
}
