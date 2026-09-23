<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
