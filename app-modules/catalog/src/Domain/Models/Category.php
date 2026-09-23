<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Domain\Enums\CategoryStatus;
use Modules\Core\Support\Concerns\BelongsToChannel;

class Category extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'name',
        'description',
        'parent_id',
        'root_category_id',
        'image_media_id',
        'icon',
        'order',
        'status',
        'level',
    ];

    protected function casts(): array
    {
        return [
            'status' => CategoryStatus::class,
            'level' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function activityTypes(): HasMany
    {
        return $this->hasMany(CategoryActivityType::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
