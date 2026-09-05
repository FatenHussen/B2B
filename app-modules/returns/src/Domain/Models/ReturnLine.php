<?php

declare(strict_types=1);

namespace Modules\Returns\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'return_request_id',
        'line_id',
        'qty',
        'reason',
        'photos',
        'condition',
    ];

    protected function casts(): array
    {
        return ['photos' => 'array'];
    }
}
