<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property bool $enabled
 * @property string|null $text
 * @property string|null $media_type
 * @property string|null $media_id
 * @property int $duration
 * @property array<string, mixed>|null $targeting
 */
class ChannelIntro extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'enabled',
        'text',
        'media_type',
        'media_id',
        'duration',
        'targeting',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'targeting' => 'array',
        ];
    }
}
