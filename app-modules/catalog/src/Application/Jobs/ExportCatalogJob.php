<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Application\Support\CatalogImportColumns;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Contracts\ProductPricingReader;
use Modules\Core\Support\Tenant;

final class ExportCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly int $channelId,
        public readonly ?string $status = null,
    ) {}

    public function handle(ProductPricingReader $pricing): void
    {
        Tenant::as($this->channelId, function () use ($pricing): void {
            $query = Product::query()->orderBy('id');
            if ($this->status) {
                $query->where('status', $this->status);
            }
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, CatalogImportColumns::headers());
            foreach ($query->cursor() as $product) {
                $price = $pricing->show((int) $product->id);
                fputcsv($handle, [
                    $product->sku,
                    $product->name_ar,
                    $product->name_en,
                    $product->barcode,
                    $product->brand_id,
                    $product->category_id,
                    $product->status->value,
                    $price['base_price'] ?? null,
                    $price['currency_id'] ?? null,
                ]);
            }
            rewind($handle);
            Storage::disk('local')->put('exports/'.$this->jobId.'.csv', stream_get_contents($handle) ?: '');
            fclose($handle);
        });
    }
}
