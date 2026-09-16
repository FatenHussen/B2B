<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Concerns\BelongsToChannel;

/**
 * @property int $id
 * @property int $supply_channel_id
 * @property int $sub_order_id
 * @property int $retailer_id
 * @property int|null $rep_id
 * @property string $no
 * @property int $total
 * @property int $paid_total
 * @property int $credited_total
 * @property string $status
 * @property Carbon|null $created_at
 */
class Invoice extends Model
{
    use BelongsToChannel;

    protected $guarded = ['status'];

    protected $fillable = [
        'supply_channel_id',
        'sub_order_id',
        'retailer_id',
        'rep_id',
        'no',
        'total',
        'paid_total',
        'credited_total',
    ];

    public function remaining(): int
    {
        return (int) $this->total - (int) $this->paid_total - (int) $this->credited_total;
    }
}
