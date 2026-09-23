<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UploadMediaRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $type = (string) $this->input('type', 'image');

        $file = ['required', 'file'];
        if ($type === 'video') {
            $file[] = 'max:51200'; // 50MB
            $file[] = 'mimetypes:video/mp4,video/webm,video/quicktime';
        } else {
            $file[] = 'max:8192'; // 8MB
            $file[] = 'mimetypes:image/jpeg,image/png,image/webp,image/gif';
        }

        return [
            'file' => $file,
            'type' => ['required', 'in:image,video'],
        ];
    }
}
