<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RetailerProfileEquipment extends Model
{
    public $timestamps = false;

    /**
     * Pinned. Doctrine's inflector treats "equipment" as uncountable, so the default
     * table for this model resolves to the singular `retailer_profile_equipment` while
     * the migration — and the plural-tables rule in CLAUDE.md — say otherwise. Its
     * sibling Modules\Reference\Domain\Models\Equipment pins `equipments` for the very
     * same reason.
     */
    protected $table = 'retailer_profile_equipments';

    protected $fillable = [
        'retailer_profile_id',
        'equipment_id',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RetailerProfile::class, 'retailer_profile_id');
    }
}
