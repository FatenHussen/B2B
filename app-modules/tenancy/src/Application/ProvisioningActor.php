<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application;

/**
 * The actor recorded on channel_events when provisioning itself moves a channel
 * to active. Not a user: there is no authenticated caller on the queue.
 */
final class ProvisioningActor
{
    public function getAuthIdentifier(): int
    {
        return 0;
    }
}
