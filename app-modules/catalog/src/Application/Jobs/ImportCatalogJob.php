<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Support\Tenant;

final class ImportCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly string $path,
        public readonly int $channelId,
    ) {}

    public function handle(): void
    {
        $full = Storage::disk('local')->path($this->path);
        if (! is_file($full)) {
            return;
        }

        Tenant::as($this->channelId, function () use ($full): void {
            $handle = fopen($full, 'r');
            if ($handle === false) {
                return;
            }
            fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                $sku = (string) ($row[0] ?? '');
                $name = (string) ($row[1] ?? $sku);
                if ($sku === '' || Product::query()->where('sku', $sku)->exists()) {
                    continue;
                }
                Product::query()->create([
                    'name_ar' => $name,
                    'sku' => $sku,
                    'status' => ProductStatus::Draft,
                ]);
            }
            fclose($handle);
        });
    }
}
