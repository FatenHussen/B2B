<?php

declare(strict_types=1);

namespace Modules\Content\Application\Support;

use Modules\Content\Domain\Models\PlatformIntro;

final class IntroPresenter
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
    public static function vacant(): array
    {
        return [
            'enabled' => false,
            'text' => null,
            'media_type' => null,
            'media_id' => null,
            'duration' => 0,
            'targeting' => ['activity_type_ids' => [], 'zone_ids' => []],
        ];
    }

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
    public static function from(PlatformIntro $row): array
    {
        $targeting = $row->targeting ?? [];

        return [
            'enabled' => (bool) $row->enabled,
            'text' => $row->text,
            'media_type' => $row->media_type,
            'media_id' => $row->media_id,
            'duration' => (int) $row->duration,
            'targeting' => [
                'activity_type_ids' => array_values((array) ($targeting['activity_type_ids'] ?? [])),
                'zone_ids' => array_values((array) ($targeting['zone_ids'] ?? [])),
            ],
        ];
    }
}
