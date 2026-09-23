<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Http\UploadedFile;
use Modules\Catalog\Domain\Models\ChannelMediaLibrary;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;

final class UploadMedia
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @return array{media_id: string, url: string, type: string, mime: string|null, size: int}
     */
    public function __invoke(object $actor, UploadedFile $file, string $type): array
    {
        if (! in_array($type, ['image', 'video'], true)) {
            InvalidFields::throw(['type' => 'catalog.media_type_invalid']);
        }

        $channelId = (int) Tenant::currentId();
        $library = ChannelMediaLibrary::query()->firstOrCreate(
            ['supply_channel_id' => $channelId],
            [],
        );

        $adder = $library
            ->addMedia($file)
            ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'upload');

        if ($type === 'image') {
            $path = $file->getRealPath();
            if (is_string($path) && $path !== '') {
                $size = @getimagesize($path);
                if (is_array($size)) {
                    $adder->withCustomProperties([
                        'width' => (int) $size[0],
                        'height' => (int) $size[1],
                    ]);
                }
            }
        }

        $media = $adder->toMediaCollection($type);

        $this->audit->record('catalog.media.upload', $actor, 'media', (int) $media->id, [
            'after' => ['type' => $type, 'mime' => $media->mime_type, 'size' => (int) $media->size],
        ], $channelId);

        return [
            'media_id' => (string) $media->id,
            'url' => $media->getUrl(),
            'type' => $type,
            'mime' => $media->mime_type,
            'size' => (int) $media->size,
        ];
    }
}
