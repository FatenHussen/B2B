<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RepProfileZone extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rep_profile_id',
        'zone_id',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RepProfile::class, 'rep_profile_id');
    }
}
