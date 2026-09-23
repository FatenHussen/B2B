<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Queries;

use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Presentation\Http\Resources\ChannelPlanResource;

final class ShowPlan
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(ChannelPlan $plan): array
    {
        return (new ChannelPlanResource($plan))->resolve();
    }
}
