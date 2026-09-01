<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Domain\Enums\RepZoneRequestStatus;

class RepZoneRequest extends Model
{
    protected $fillable = [
        'rep_id',
        'zone_id',
        'note',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RepZoneRequestStatus::class,
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RepProfile::class, 'rep_id');
    }
}
