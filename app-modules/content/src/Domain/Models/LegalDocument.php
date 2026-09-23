<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $version
 * @property string $body_ar
 * @property Carbon $effective_from
 * @property bool $requires_reconsent
 * @property int|null $approved_by
 */
class LegalDocument extends Model
{
    protected $fillable = [
        'type',
        'version',
        'body_ar',
        'effective_from',
        'requires_reconsent',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'requires_reconsent' => 'boolean',
            'approved_by' => 'integer',
        ];
    }
}
