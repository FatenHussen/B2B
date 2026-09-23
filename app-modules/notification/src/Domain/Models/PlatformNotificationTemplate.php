<?php

declare(strict_types=1);

namespace Modules\Notification\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformNotificationTemplate extends Model
{
    protected $fillable = ['key', 'title', 'body'];
}
