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

    public function sections(): HasMany
    {
        return $this->hasMany(CartSection::class);
    }
}
