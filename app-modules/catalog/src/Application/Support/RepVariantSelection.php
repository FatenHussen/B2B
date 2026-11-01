<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Support;

use Illuminate\Support\Collection;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductVariant;

/**
 * Axes and values the rep picker can offer. A value appears only when at least
 * one active variant carries it — a full cartesian is not assumed.
 */
final class RepVariantSelection
{
    public function isOrderable(ProductVariant $variant): bool
    {
        return $variant->status === 'active';
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, ProductVariant>
     */
    public function orderable(Collection $variants): Collection
    {
        return $variants->filter(fn (ProductVariant $variant) => $this->isOrderable($variant))->values();
    }

    /**
     * @return list<array{attribute: string|null, value: string}>
     */
    public function attributes(ProductVariant $variant): array
    {
        $combination = $variant->combination;
        if (! is_array($combination) || $combination === []) {
            return [];
        }

        $attributes = [];
        foreach ($combination as $key => $value) {
            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }

            $named = is_string($key) && ! is_numeric((string) $key);
            $attributes[] = [
                'attribute' => $named ? $key : null,
                'value' => (string) $value,
            ];
        }

        return $attributes;
    }

    public function label(ProductVariant $variant): string
    {
        $attributes = $this->attributes($variant);
        if ($attributes === []) {
            $sku = $variant->sku;

            return $sku !== null && $sku !== '' ? (string) $sku : 'متغير';
        }

        $parts = array_map(
            fn (array $attribute): string => $attribute['attribute'] !== null
                ? $attribute['attribute'].': '.$attribute['value']
                : $attribute['value'],
            $attributes,
        );

        return implode(' / ', $parts);
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @return list<array{attribute: string|null, values: list<string>}>
     */
    public function map(Product $product, Collection $variants): array
    {
        $present = $this->valuesInUse($variants);
        $fromAxes = $this->mapFromAxes($product, $present);
        if ($fromAxes !== []) {
            return $fromAxes;
        }

        $rows = [];
        foreach ($present as $name => $values) {
            $rows[] = [
                'attribute' => $name === '' ? null : $name,
                'values' => array_keys($values),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @return array<string, array<string, true>>
     */
    private function valuesInUse(Collection $variants): array
    {
        $present = [];
        foreach ($variants as $variant) {
            foreach ($this->attributes($variant) as $attribute) {
                $present[$attribute['attribute'] ?? ''][$attribute['value']] = true;
            }
        }

        return $present;
    }

    /**
     * @param  array<string, array<string, true>>  $present
     * @return list<array{attribute: string|null, values: list<string>}>
     */
    private function mapFromAxes(Product $product, array $present): array
    {
        if (! $product->relationLoaded('variantAxes') || $product->variantAxes->isEmpty()) {
            return [];
        }

        $rows = [];
        foreach ($product->variantAxes as $axis) {
            $values = [];
            foreach ($axis->values as $value) {
                $text = (string) $value->value;
                if (isset($present[$axis->name][$text])) {
                    $values[] = $text;
                }
            }

            if ($values !== []) {
                $rows[] = ['attribute' => (string) $axis->name, 'values' => $values];
            }
        }

        return $rows;
    }
}
