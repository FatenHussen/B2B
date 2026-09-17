<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reference\Domain\Models\SaleUnit;

/**
 * @mixin SaleUnit
 */
final class SaleUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'abbr' => $this->abbr,
            'default_factor' => $this->default_factor,
            'status' => $this->status->value,
        ];
    }
}
