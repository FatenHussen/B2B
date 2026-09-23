<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One row per channel — Spatie media attaches here so uploads stay channel-scoped
 * without importing Tenancy's SupplyChannel model (rule 1).
 */
class ChannelMediaLibrary extends Model implements HasMedia
{
    use BelongsToChannel;
    use InteractsWithMedia;

    protected $fillable = ['supply_channel_id'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->acceptsMimeTypes([
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ]);
        $this->addMediaCollection('video')->acceptsMimeTypes([
            'video/mp4',
            'video/webm',
            'video/quicktime',
        ]);
    }
}
