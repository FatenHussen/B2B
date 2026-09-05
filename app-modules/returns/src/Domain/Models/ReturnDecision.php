<?php

declare(strict_types=1);

namespace Modules\Returns\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnDecision extends Model
{
    protected $fillable = [
        'return_request_id',
        'decision',
        'reason',
        'actor_type',
        'actor_id',
    ];
}
