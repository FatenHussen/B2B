<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Queries;

use Modules\Core\Contracts\ChannelUserDirectory;
use Modules\Tenancy\Domain\Models\SupplyChannel;

final class ShowChannelUsers
{
    public function __construct(private readonly ChannelUserDirectory $users) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(SupplyChannel $channel): array
    {
        return $this->users->listForChannel((int) $channel->id);
    }
}
