<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Latest GPS ping for a rep — Delivery owns pings; channel dashboard reads via this.
 */
interface RepLiveLocation
{
    /**
     * @return array{lat: float|null, lng: float|null, at: string|null, on_duty: bool}|null
     *                                                                                      null when the rep is unknown to duty lookup; otherwise always returns on_duty.
     */
    public function latest(int $repUserId): ?array;
}
