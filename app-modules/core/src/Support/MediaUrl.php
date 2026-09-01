<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaUrl
{
    public static function of(?int $mediaId): ?string
    {
        if ($mediaId === null || $mediaId < 1) {
            return null;
        }

        $media = Media::query()->find($mediaId);

        return $media instanceof Media ? $media->getUrl() : null;
    }
}
