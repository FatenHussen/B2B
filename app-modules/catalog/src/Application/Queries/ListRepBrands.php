<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Queries;

use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Support\Tenant;

final class ListRepBrands
{
    public function __construct(private readonly RepSellingContext $selling) {}

    /**
     * @return list<array{id: int, name: string}>
     */
    public function __invoke(object $user): array
    {
        $ctx = $this->selling->for($user);
        $rows = [];
        foreach ($ctx['channel_ids'] as $channelId) {
            if ($channelId <= 0) {
                continue;
            }
            $found = Tenant::as($channelId, fn () => Brand::query()
                ->where('status', BrandStatus::Active)
                ->orderBy('order')
                ->orderBy('name_ar')
                ->get());
            foreach ($found as $brand) {
                $rows[(int) $brand->id] = [
                    'id' => (int) $brand->id,
                    'name' => (string) $brand->name_ar,
                ];
            }
        }

        return array_values($rows);
    }
}
