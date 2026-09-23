<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface FeatureFlags
{
    /**
     * @return array<string, bool>
     */
    public function forChannel(int $channelId): array;

    /**
     * @return array<string, bool>
     */
    public function forApp(string $scope): array;
}
