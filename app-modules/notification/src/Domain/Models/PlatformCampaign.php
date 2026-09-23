<?php

declare(strict_types=1);

namespace Modules\Notification\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformCampaign extends Model
{
    protected $fillable = [
        'title', 'body', 'targeting', 'channels', 'scheduled_at', 'stats',
    ];

    /** @var list<string> */
    protected $guarded = ['status'];

    protected function casts(): array
    {
        return [
            'targeting' => 'array',
            'channels' => 'array',
            'stats' => 'array',
            'scheduled_at' => 'datetime',
        ];
    }
}
