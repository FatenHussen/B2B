<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property string $name
 * @property string $source
 * @property string|null $source_ref
 * @property string|null $algorithm
 * @property list<string>|null $placements
 * @property int $items_count
 * @property bool $show_all_button
 * @property array<string, mixed>|null $targeting
 */
class Slider extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'name',
        'source',
        'source_ref',
        'algorithm',
        'placements',
        'items_count',
        'show_all_button',
        'targeting',
    ];

    protected function casts(): array
    {
        return [
            'placements' => 'array',
            'targeting' => 'array',
            'show_all_button' => 'boolean',
        ];
    }
}
