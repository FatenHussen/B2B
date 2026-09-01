<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Catalog\Domain\Models\RetailerShortage;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\RetailerShoppingContext;

final class ListShortages
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly CatalogProductLookup $products,
    ) {}

    public function __invoke(object $user, Request $request): LengthAwarePaginator
    {
        $ctx = $this->shopping->for($user);

        return RetailerShortage::query()
            ->where('retailer_id', $ctx['retailer_id'])
            ->orderByDesc('id')
            ->paginate(min((int) $request->get('per_page', 25), 100));
    }

    /**
     * @return array{id: int, product_id: int, name: string|null, note: string|null}
     */
    public function map(RetailerShortage $row): array
    {
        return [
            'id' => (int) $row->id,
            'product_id' => (int) $row->product_id,
            'name' => $this->products->name((int) $row->product_id),
            'note' => $row->note,
        ];
    }
}
