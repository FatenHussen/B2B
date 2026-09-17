<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $type
 * @property string|null $file_name
 * @property string|null $file_hash
 * @property int $rows_total
 * @property int $rows_created
 * @property int $rows_updated
 * @property int $rows_failed
 * @property array<int, array<string, mixed>>|null $errors
 * @property string|null $actor_type
 * @property int|null $actor_id
 */
class ImportBatch extends Model
{
    protected $table = 'import_batches';

    protected $fillable = [
        'type',
        'file_name',
        'file_hash',
        'rows_total',
        'rows_created',
        'rows_updated',
        'rows_failed',
        'errors',
        'actor_type',
        'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'rows_total' => 'integer',
            'rows_created' => 'integer',
            'rows_updated' => 'integer',
            'rows_failed' => 'integer',
        ];
    }
}
