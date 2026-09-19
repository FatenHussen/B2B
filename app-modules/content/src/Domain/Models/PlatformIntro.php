<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide default intro. One row (`slot = default`). Not channel-owned:
 * the back office writes it with no tenant, and a channel that never set its
 * own intro is the audience that reads it later.
 *
 * @property int $id
 * @property string $slot
 * @property bool $enabled
 * @property string|null $text
 * @property string|null $media_type
 * @property string|null $media_id
 * @property int $duration
 * @property array<string, mixed>|null $targeting
 */
class PlatformIntro extends Model
{
    public const SLOT = 'default';

    protected $fillable = [
        'slot',
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
            'duration' => 'integer',
            'targeting' => 'array',
        ];
    }
}
