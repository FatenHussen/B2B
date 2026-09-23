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
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Catalog\Domain\Models\Category;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Core\Contracts\PricingDraft;
use Modules\Core\Contracts\ProductPricingWriter;
use Modules\Core\Support\Tenant;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class ImportCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly string $path,
        public readonly int $channelId,
    ) {}

    public function handle(ProductPricingWriter $pricing): void
    {
        $full = Storage::disk('local')->path($this->path);
        if (! is_file($full)) {
            return;
        }

        Tenant::as($this->channelId, function () use ($full, $pricing): void {
            $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
            $rows = $ext === 'csv'
                ? $this->readCsv($full)
                : $this->readSpreadsheet($full);

            if ($rows === []) {
                return;
            }

            $header = array_values(array_shift($rows) ?? []);
            $headerIndex = CatalogImportColumns::headerIndex($header);
            $rows = array_map(static fn (array $row): array => array_values($row), $rows);

            foreach ($rows as $row) {
                $mapped = CatalogImportColumns::mapRow($row, $headerIndex);
                $sku = trim((string) ($mapped['sku'] ?? ''));
                $name = trim((string) ($mapped['name_ar'] ?? ''));
                if ($sku === '' || $name === '' || Product::query()->where('sku', $sku)->exists()) {
                    continue;
                }

                $barcode = trim((string) ($mapped['barcode'] ?? ''));
                if ($barcode !== '' && (
                    Product::query()->where('barcode', $barcode)->exists()
                    || ProductVariant::query()->where('barcode', $barcode)->whereHas('product')->exists()
                )) {
                    continue;
                }

                $brandId = $mapped['brand_id'] !== null && $mapped['brand_id'] !== ''
                    ? (int) $mapped['brand_id']
                    : null;
                if ($brandId !== null && Brand::query()->whereKey($brandId)->doesntExist()) {
                    $brandId = null;
                }

                $categoryId = $mapped['category_id'] !== null && $mapped['category_id'] !== ''
                    ? (int) $mapped['category_id']
                    : null;
                if ($categoryId !== null && Category::query()->whereKey($categoryId)->doesntExist()) {
                    $categoryId = null;
                }

                $status = ProductStatus::tryFrom(trim((string) ($mapped['status'] ?? 'draft')))
                    ?? ProductStatus::Draft;

                $product = Product::query()->create([
                    'name_ar' => $name,
                    'name_en' => $mapped['name_en'] !== null && $mapped['name_en'] !== ''
                        ? (string) $mapped['name_en']
                        : null,
                    'sku' => $sku,
                    'barcode' => $barcode !== '' ? $barcode : null,
                    'brand_id' => $brandId,
                    'category_id' => $categoryId,
                    'status' => $status,
                ]);

                if ($mapped['base_price'] !== null && $mapped['base_price'] !== '' && is_numeric($mapped['base_price'])) {
                    $currencyId = $mapped['currency_id'] !== null && $mapped['currency_id'] !== ''
                        ? (int) $mapped['currency_id']
                        : 0;
                    $pricing->replace($this->channelId, (int) $product->id, PricingDraft::fromArray([
                        'type' => 'simple',
                        'base_price' => (int) $mapped['base_price'],
                        'currency_id' => $currencyId,
                        'tiers' => [],
                    ]));
                }
            }
        });
    }

    /**
     * @return list<array<int, mixed>>
     */
    private function readCsv(string $full): array
    {
        $handle = fopen($full, 'r');
        if ($handle === false) {
            return [];
        }
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array<int|string, mixed>>
     */
    private function readSpreadsheet(string $full): array
    {
        $sheet = IOFactory::load($full)->getActiveSheet()->toArray(null, true, true, true);

        return array_values($sheet);
    }
}
