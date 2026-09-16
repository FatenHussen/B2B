<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property string $media_type
 * @property string $media_id
 * @property array<string, mixed>|null $link
 * @property list<string> $placements
 * @property array<string, mixed>|null $targeting
 * @property int $order
 * @property int $weight
 * @property int $impressions
 * @property int $clicks
 */
class Banner extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'media_type',
        'media_id',
        'link',
        'placements',
        'targeting',
        'starts_at',
        'ends_at',
        'order',
        'weight',
        'impressions',
        'clicks',
    ];

    protected function casts(): array
    {
        return [
            'link' => 'array',
            'placements' => 'array',
            'targeting' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
