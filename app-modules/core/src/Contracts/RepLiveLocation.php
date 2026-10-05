<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Latest GPS ping for a rep — Delivery owns pings; channel dashboard reads via this.
 */
interface RepLiveLocation
{
    /**
     * @return array{lat: float|null, lng: float|null, at: string|null, on_duty: bool}
     */
    public function latest(int $repUserId): array;
}
