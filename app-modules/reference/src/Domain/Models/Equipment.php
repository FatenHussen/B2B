<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Reference\Domain\Enums\RefStatus;

/**
 * @property int $id
 * @property string $name
 * @property string|null $icon
 * @property string|null $description
 * @property int $order
 * @property RefStatus $status
 */
class Equipment extends Model
{
    protected $table = 'equipments';

    /** `status` is absent on purpose (rule 8): EP-AD-043E is the only way it moves. */
    protected $fillable = ['name', 'icon', 'description', 'order'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
        'order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => RefStatus::class,
            'order' => 'integer',
        ];
    }
}
