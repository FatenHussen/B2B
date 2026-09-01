<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Catalog\Application\Jobs\ExportCatalogJob;
use Modules\Catalog\Application\Jobs\ImportCatalogJob;
use Modules\Catalog\Domain\Models\Product;
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
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $header = array_shift($rows) ?? [];
        $errors = [];
        $duplicates = [];
        $preview = [];
        $seen = [];
        $rowNum = 1;

        foreach (array_slice($rows, 0, 500) as $row) {
            $rowNum++;
            $sku = (string) ($row['A'] ?? $row[0] ?? '');
            $name = (string) ($row['B'] ?? $row[1] ?? '');
            if ($sku === '') {
                $errors[] = ['row' => $rowNum, 'message' => __('catalog.import_sku_required')];
                continue;
            }
            if (isset($seen[$sku]) || Product::query()->where('sku', $sku)->exists()) {
                $duplicates[] = $sku;
            }
            $seen[$sku] = true;
            $preview[] = ['sku' => $sku, 'name_ar' => $name];
        }

        if (! $dryRun) {
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
