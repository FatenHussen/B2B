<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Enums;

enum ProductMediaRole: string
{
    case Image = 'image';
    case Primary = 'primary';
    case Video = 'video';
}
