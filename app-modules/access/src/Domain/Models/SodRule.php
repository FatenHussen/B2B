<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $permission_a
 * @property string $permission_b
 * @property string $reason
 * @property array<int, mixed>|null $exceptions
 */
class SodRule extends Model
{
    protected $fillable = [
        'code',
        'permission_a',
        'permission_b',
        'reason',
        'exceptions',
    ];

    protected function casts(): array
    {
        return [
            'exceptions' => 'array',
        ];
    }
}
