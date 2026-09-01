<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Channel × zone coverage. Table owned by reference; tenancy reads ids only.
 */
final class ChannelCoverage extends Model
{
    protected $table = 'channel_zone';

    public $timestamps = true;

    protected $fillable = [
        'supply_channel_id',
        'zone_id',
    ];
}
