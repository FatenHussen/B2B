<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Illuminate\Http\Request;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Contracts\RepSellingContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowRepProduct
{
    public function __construct(
        private readonly RepSellingContext $selling,
        private readonly ListRepProducts $list,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user, Request $request, int $id): array
    {
        $ctx = $this->selling->for($user);
        $channelIds = $ctx['channel_ids'] === [] ? [0] : $ctx['channel_ids'];

        // Lifted, per rule 10: the rep app sets no tenant; `whereIn('supply_channel_id', …)`
        // below is the rep's own channel, and the isolation.
        $product = Product::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $channelIds)
            ->where('status', ProductStatus::Active)
            ->with(['brand', 'variants', 'media'])
            ->whereKey($id)
            ->first();

        if ($product === null) {
            throw new NotFoundHttpException;
        }

        return $this->list->detail($user, $product);
    }
}
