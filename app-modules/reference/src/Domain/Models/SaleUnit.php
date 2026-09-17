<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Reference\Domain\Enums\RefStatus;

/**
 * @property int $id
 * @property string $name
 * @property string|null $abbr
 * @property int $default_factor
 * @property RefStatus $status
 */
class SaleUnit extends Model
{
    /** `status` is absent on purpose (rule 8): EP-AD-043D is the only way it moves. */
    protected $fillable = ['name', 'abbr', 'default_factor'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
        'default_factor' => 1,
    ];

    protected function casts(): array
    {
        return [
            'default_factor' => 'integer',
            'status' => RefStatus::class,
        ];
    }
}
