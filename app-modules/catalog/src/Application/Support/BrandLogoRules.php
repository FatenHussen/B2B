<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Support;

use Modules\Core\Support\InvalidFields;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class BrandLogoRules
{
    public const MIN_EDGE = 512;

    public static function assert(int $mediaId): void
    {
        $media = Media::query()->find($mediaId);
        if ($media === null) {
            InvalidFields::throw(['logo' => 'catalog.media_not_found']);
        }

        if (! str_starts_with((string) $media->mime_type, 'image/')) {
            InvalidFields::throw(['logo' => 'catalog.brand_logo_not_image']);
        }

        $width = (int) ($media->getCustomProperty('width') ?? 0);
        $height = (int) ($media->getCustomProperty('height') ?? 0);

        if ($width < 1 || $height < 1) {
            $path = $media->getPath();
            if (is_string($path) && is_file($path)) {
                $size = @getimagesize($path);
                if (is_array($size)) {
                    $width = (int) $size[0];
                    $height = (int) $size[1];
                }
            }
        }

        if ($width !== $height || $width < self::MIN_EDGE) {
            InvalidFields::throw(['logo' => 'catalog.brand_logo_dimensions']);
        }
    }
}
