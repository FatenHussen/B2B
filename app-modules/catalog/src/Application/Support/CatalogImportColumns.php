<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Support;

final class CatalogImportColumns
{
    /** @return list<string> */
    public static function headers(): array
    {
        return [
            'sku',
            'name_ar',
            'name_en',
            'barcode',
            'brand_id',
            'category_id',
            'status',
            'base_price',
            'currency_id',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sampleRow(): array
    {
        return [
            'sku' => 'OIL-SUN-1L',
            'name_ar' => 'زيت دوار الشمس',
            'name_en' => 'Sunflower Oil',
            'barcode' => '6291000000000',
            'brand_id' => 12,
            'category_id' => 340,
            'status' => 'draft',
            'base_price' => 12000,
            'currency_id' => 1,
        ];
    }

    /**
     * Map a spreadsheet/CSV row (associative A/B… or numeric) onto named columns.
     *
     * @param  array<int|string, mixed>  $row
     * @param  array<string, int>|null  $headerIndex  column name → 0-based index when header present
     * @return array<string, mixed>
     */
    public static function mapRow(array $row, ?array $headerIndex = null): array
    {
        $headers = self::headers();
        $out = [];

        if ($headerIndex !== null) {
            foreach ($headers as $name) {
                $idx = $headerIndex[$name] ?? null;
                $out[$name] = $idx !== null ? ($row[$idx] ?? null) : null;
            }

            return $out;
        }

        // Letter keys from PhpSpreadsheet (A, B, …) or numeric fgetcsv.
        $letterKeys = range('A', 'I');
        foreach ($headers as $i => $name) {
            if (array_key_exists($letterKeys[$i], $row)) {
                $out[$name] = $row[$letterKeys[$i]];
            } elseif (array_key_exists($i, $row)) {
                $out[$name] = $row[$i];
            } else {
                $out[$name] = null;
            }
        }

        return $out;
    }

    /**
     * @param  array<int|string, mixed>  $headerRow
     * @return array<string, int>|null
     */
    public static function headerIndex(array $headerRow): ?array
    {
        $normalized = [];
        foreach ($headerRow as $key => $value) {
            $name = strtolower(trim((string) $value));
            if ($name === '') {
                continue;
            }
            $normalized[$name] = is_int($key) ? $key : (ord((string) $key) - ord('A'));
        }

        if (! isset($normalized['sku'])) {
            return null;
        }

        $index = [];
        foreach (self::headers() as $i => $name) {
            $index[$name] = $normalized[$name] ?? $i;
        }

        return $index;
    }
}
