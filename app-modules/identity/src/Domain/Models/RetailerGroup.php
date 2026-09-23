<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property string $name
 */
class RetailerGroup extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'name',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(RetailerGroupMember::class);
    }
}
