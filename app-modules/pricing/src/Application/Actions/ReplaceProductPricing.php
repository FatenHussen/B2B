<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Actions;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\PricingDraft;
use Modules\Core\Contracts\ProductPricingWriter;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\Tenant;
use Modules\Pricing\Domain\Models\PriceChangeLog;
use Modules\Pricing\Domain\Models\ProductBasePrice;

final class ReplaceProductPricing
{
    public function __construct(
        private readonly ProductPricingWriter $writer,
        private readonly CatalogProductLookup $products,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{price_change_log_id: int}
     */
    public function __invoke(object $actor, int $productId, array $data): array
    {
        $channelId = (int) Tenant::currentId();
        if (! $this->products->exists($productId) || $this->products->channelId($productId) !== $channelId) {
            abort(404);
        }
        $before = (int) (ProductBasePrice::query()->where('product_id', $productId)->value('base_price') ?? 0);

        $this->writer->replace($channelId, $productId, PricingDraft::fromArray($data));

        $after = (int) $data['base_price'];
        $log = PriceChangeLog::query()->create([
            'product_id' => $productId,
            'actor_user_id' => (int) $actor->getAuthIdentifier(),
            'before' => $before,
            'after' => $after,
            'reason' => $data['reason'] ?? null,
            'at' => now(),
        ]);

        $this->audit->record('pricing.product.update', $actor, 'product', $productId, [
            'before' => ['base_price' => $before],
            'after' => ['base_price' => $after],
        ], $channelId);

        return ['price_change_log_id' => (int) $log->id];
    }
}
