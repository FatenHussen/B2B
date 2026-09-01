<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RetailerProfileEquipment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'retailer_profile_id',
        'equipment_id',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RetailerProfile::class, 'retailer_profile_id');
    }
}
