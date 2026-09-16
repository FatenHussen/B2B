<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Ordering\Domain\Enums\CartStatus;

class Cart extends Model
{
    protected $fillable = ['owner_type', 'owner_id', 'status'];

    protected function casts(): array
    {
        return ['status' => CartStatus::class];
    }

    /**
     * The channel scope is lifted on this relation, with the reason here (rule 10,
     * BE-C12). A cart belongs to one app user — `owner_type`, `owner_id` — and is only ever
     * reached through that owner (`CartAssembler::activeFor`). A section cannot belong to
     * another owner than its cart, so ownership is the isolation; `channel_id` on a
     * section is the split key of a multi-channel cart, not a boundary. `/app/*` sets no
     * tenant, and must not: a retailer's cart holds several channels at once.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(CartSection::class)->withoutGlobalScope('channel');
    }
}
