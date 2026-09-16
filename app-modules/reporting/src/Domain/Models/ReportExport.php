<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property string $job_id
 * @property string $type
 * @property string $format
 * @property array<string, mixed>|null $filters
 * @property string $status
 */
class ReportExport extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'job_id',
        'type',
        'format',
        'filters',
        'status',
    ];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
