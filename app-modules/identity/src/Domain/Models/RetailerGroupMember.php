<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $retailer_group_id
 * @property int $retailer_id
 */
class RetailerGroupMember extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'retailer_group_id',
        'retailer_id',
    ];
}
