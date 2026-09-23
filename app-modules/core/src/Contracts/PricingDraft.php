<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

final class PricingDraft
{
    /**
     * @param  list<array{from: int, to: int|null, price: int}>  $tiers
     */
    public function __construct(
        public readonly string $type,
        public readonly int $basePrice,
        public readonly int $currencyId,
        public readonly array $tiers = [],
        public readonly int $taxPercent = 0,
    ) {}

    /**
     * @param  array{type?: string, base_price?: int, currency_id?: int, tax_percent?: int, tiers?: list<array{from?: int, to?: int|null, price?: int}>}  $payload
     */
    public static function fromArray(array $payload): self
    {
        $tiers = [];
        foreach ($payload['tiers'] ?? [] as $tier) {
            $tiers[] = [
                'from' => (int) ($tier['from'] ?? 1),
                'to' => array_key_exists('to', $tier) && $tier['to'] !== null ? (int) $tier['to'] : null,
                'price' => (int) ($tier['price'] ?? 0),
            ];
        }

        return new self(
            (string) ($payload['type'] ?? 'simple'),
            (int) ($payload['base_price'] ?? 0),
            (int) ($payload['currency_id'] ?? 0),
            $tiers,
            max(0, min(100, (int) ($payload['tax_percent'] ?? 0))),
        );
    }
}
