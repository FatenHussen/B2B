<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Catalog\Application\Jobs\ImportCatalogJob;
use Modules\Catalog\Application\Support\CatalogImportColumns;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Catalog\Domain\Models\Category;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\Tenant;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class ImportCatalog
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @return array{preview: list<array<string, mixed>>, errors: list<array{row: int, message: string}>, duplicates: list<string>}
     */
    public function __invoke(object $actor, UploadedFile $file, bool $dryRun): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath() ?: $file->getPathname());
        $raw = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $headerRow = array_values(array_shift($raw) ?? []);
        $headerIndex = CatalogImportColumns::headerIndex($headerRow);
        $rows = array_map(static fn (array $row): array => array_values($row), $raw);

        $errors = [];
        $duplicates = [];
        $preview = [];
        $seenSkus = [];
        $seenBarcodes = [];
        $rowNum = 1;

        foreach (array_slice($rows, 0, 500) as $row) {
            $rowNum++;
            $mapped = CatalogImportColumns::mapRow($row, $headerIndex);
            $sku = trim((string) ($mapped['sku'] ?? ''));
            $name = trim((string) ($mapped['name_ar'] ?? ''));
            $barcode = trim((string) ($mapped['barcode'] ?? ''));

            if ($sku === '') {
                $errors[] = ['row' => $rowNum, 'message' => __('catalog.import_sku_required')];

                continue;
            }
            if ($name === '') {
                $errors[] = ['row' => $rowNum, 'message' => __('catalog.import_name_required')];

                continue;
            }

            if (isset($seenSkus[$sku]) || Product::query()->where('sku', $sku)->exists()) {
                $duplicates[] = $sku;
            }
            $seenSkus[$sku] = true;

            if ($barcode !== '') {
                if (isset($seenBarcodes[$barcode])
                    || Product::query()->where('barcode', $barcode)->exists()
                    || ProductVariant::query()->where('barcode', $barcode)->whereHas('product')->exists()) {
                    $duplicates[] = $barcode;
                    $errors[] = ['row' => $rowNum, 'message' => __('catalog.import_barcode_taken')];
                }
                $seenBarcodes[$barcode] = true;
            }

            $statusRaw = trim((string) ($mapped['status'] ?? 'draft'));
            if ($statusRaw !== '' && ProductStatus::tryFrom($statusRaw) === null) {
                $errors[] = ['row' => $rowNum, 'message' => __('catalog.import_invalid_status')];
            }

            if ($mapped['base_price'] !== null && $mapped['base_price'] !== '' && ! is_numeric($mapped['base_price'])) {
                $errors[] = ['row' => $rowNum, 'message' => __('catalog.import_invalid_base_price')];
            }

            if ($mapped['brand_id'] !== null && $mapped['brand_id'] !== ''
                && Brand::query()->whereKey((int) $mapped['brand_id'])->doesntExist()) {
                $errors[] = ['row' => $rowNum, 'message' => __('catalog.brand_not_found')];
            }
            if ($mapped['category_id'] !== null && $mapped['category_id'] !== ''
                && Category::query()->whereKey((int) $mapped['category_id'])->doesntExist()) {
                $errors[] = ['row' => $rowNum, 'message' => __('catalog.category_not_found')];
            }

            $preview[] = [
                'sku' => $sku,
                'name_ar' => $name,
                'name_en' => $mapped['name_en'] !== null ? (string) $mapped['name_en'] : null,
                'barcode' => $barcode !== '' ? $barcode : null,
                'brand_id' => $mapped['brand_id'] !== null && $mapped['brand_id'] !== '' ? (int) $mapped['brand_id'] : null,
                'category_id' => $mapped['category_id'] !== null && $mapped['category_id'] !== '' ? (int) $mapped['category_id'] : null,
                'status' => $statusRaw !== '' ? $statusRaw : 'draft',
                'base_price' => $mapped['base_price'] !== null && $mapped['base_price'] !== '' ? (int) $mapped['base_price'] : null,
                'currency_id' => $mapped['currency_id'] !== null && $mapped['currency_id'] !== '' ? (int) $mapped['currency_id'] : null,
            ];
        }

        $duplicates = array_values(array_unique($duplicates));

        if (! $dryRun && $errors === []) {
            $jobId = 'job_import_'.Str::lower((string) Str::ulid());
            $path = $file->storeAs('imports', $jobId.'.'.$file->getClientOriginalExtension(), 'local');
            ImportCatalogJob::dispatch($jobId, (string) $path, (int) Tenant::currentId())
                ->onQueue('default');
            $this->audit->record('catalog.import', $actor, 'catalog_import', null, [
                'after' => ['job_id' => $jobId],
            ], Tenant::currentId());
        }

        return compact('preview', 'errors', 'duplicates');
    }
}
