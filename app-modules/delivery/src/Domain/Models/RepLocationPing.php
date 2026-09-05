<?php

declare(strict_types=1);

namespace Modules\Delivery\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RepLocationPing extends Model
{
    public $timestamps = false;

    protected $fillable = ['rep_id', 'lat', 'lng', 'at', 'accuracy'];

    protected function casts(): array
    {
        return ['at' => 'datetime'];
    }
}
