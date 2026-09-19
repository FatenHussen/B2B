<?php

declare(strict_types=1);

namespace Modules\Content\Application\Queries;

use Modules\Content\Application\Support\IntroPresenter;
use Modules\Content\Domain\Models\PlatformIntro;

final class ShowPlatformIntro
{
    /**
     * @return array{
     *     enabled: bool,
     *     text: string|null,
     *     media_type: string|null,
     *     media_id: string|null,
     *     duration: int,
     *     targeting: array{activity_type_ids: list<int|string>, zone_ids: list<int|string>}
     * }
     */
    public function __invoke(): array
    {
        $row = PlatformIntro::query()->where('slot', PlatformIntro::SLOT)->first();
        if ($row === null) {
            return IntroPresenter::vacant();
        }

        return IntroPresenter::from($row);
    }
}
