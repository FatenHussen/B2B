<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackingJob extends Model
{
    protected $fillable = [
        'picking_list_id',
        'status',
        'packages_count',
        'weight_gram',
        'flags',
        'mismatches',
    ];

    protected function casts(): array
    {
        return [
            'flags' => 'array',
            'mismatches' => 'array',
        ];
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }
}
