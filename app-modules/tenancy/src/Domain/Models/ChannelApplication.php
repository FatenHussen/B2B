<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Tenancy\Domain\Enums\ChannelApplicationStatus;

/**
 * @property int $id
 * @property string $name
 * @property string|null $legal_form
 * @property string|null $cr_number
 * @property array<int, mixed>|null $documents
 * @property array<string, mixed>|null $contact
 * @property ChannelApplicationStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $reason
 * @property int|null $channel_id
 */
class ChannelApplication extends Model
{
    protected $fillable = [
        'name',
        'legal_form',
        'cr_number',
        'documents',
        'contact',
        'decided_by',
        'decided_at',
        'reason',
        'channel_id',
    ];

    /** @var list<string> */
    protected $guarded = ['status'];

    protected function casts(): array
    {
        return [
            'documents' => 'array',
            'contact' => 'array',
            'status' => ChannelApplicationStatus::class,
            'decided_at' => 'datetime',
            'decided_by' => 'integer',
            'channel_id' => 'integer',
        ];
    }
}
