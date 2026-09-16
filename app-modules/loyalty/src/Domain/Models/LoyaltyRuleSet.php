<?php

declare(strict_types=1);

namespace Modules\Loyalty\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property list<array<string, mixed>> $retailer_rules
 * @property list<array<string, mixed>> $rep_rules
 * @property list<array<string, mixed>> $tiers
 */
class LoyaltyRuleSet extends Model
{
    use BelongsToChannel;

    protected $fillable = [
        'supply_channel_id',
        'retailer_rules',
        'rep_rules',
        'tiers',
    ];

    protected function casts(): array
    {
        return [
            'retailer_rules' => 'array',
            'rep_rules' => 'array',
            'tiers' => 'array',
        ];
    }
}
