<?php

declare(strict_types=1);

namespace Modules\Content\Application\Actions;

use Modules\Content\Domain\Models\PlatformIntro;

final class UpdatePlatformIntro
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{enabled: bool}
     */
    public function __invoke(array $data): array
    {
        $enabled = (bool) $data['enabled'];

        PlatformIntro::query()->updateOrCreate(
            ['slot' => PlatformIntro::SLOT],
            [
                'enabled' => $enabled,
                'text' => $data['text'] ?? null,
                'media_type' => $data['media_type'] ?? null,
                'media_id' => $data['media_id'] ?? null,
                'duration' => (int) ($data['duration'] ?? 0),
                'targeting' => $data['targeting'] ?? null,
            ],
        );

        return ['enabled' => $enabled];
    }
}
