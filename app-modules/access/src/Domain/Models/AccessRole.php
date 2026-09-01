<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Models;

use Modules\Access\Domain\Enums\RoleStatus;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string|null $label
 * @property string $guard_name
 * @property string $system
 * @property RoleStatus $status
 * @property bool $is_builtin
 * @property string|null $description
 * @property int|null $created_by
 * @property int|string|null $team_id
 */
class AccessRole extends SpatieRole
{
    protected $fillable = [
        'name',
        'label',
        'guard_name',
        'system',
        'status',
        'is_builtin',
        'description',
        'created_by',
        'team_id',
    ];

    protected function casts(): array
    {
        return [
            'is_builtin' => 'boolean',
            'status' => RoleStatus::class,
        ];
    }

    public function key(): string
    {
        return $this->name;
    }
}
