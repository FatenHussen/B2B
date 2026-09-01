<?php

declare(strict_types=1);

namespace Modules\Access\Application\Queries;

use Modules\Access\Application\Services\SodChecker;
use Modules\Access\Domain\PermissionCatalog;

final class PreviewRole
{
    public function __construct(private readonly SodChecker $sod) {}

    /**
     * @param  array{permissions: list<string>}  $data
     * @return array{summary_ar: string, sod_conflicts: list<array<string, mixed>>}
     */
    public function __invoke(array $data): array
    {
        $codes = array_values(array_unique($data['permissions']));
        $this->sod->assertCatalogCodes($codes);

        $names = [];
        foreach ($codes as $code) {
            $names[] = PermissionCatalog::get($code)['name_ar'] ?? $code;
        }

        $summary = $names === []
            ? ''
            : 'سيستطيع '.implode(' و', $names);

        return [
            'summary_ar' => $summary,
            'sod_conflicts' => $this->sod->conflictsIn($codes),
        ];
    }
}
