<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $audience
 * @property string $title
 * @property string $body
 * @property string $status
 * @property int $order
 */
class HelpGuide extends Model
{
    protected $fillable = ['audience', 'title', 'body', 'status', 'order'];

    protected function casts(): array
    {
        return ['order' => 'integer'];
    }
}
