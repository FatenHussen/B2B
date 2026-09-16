<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $credit_note_id
 * @property int|null $invoice_line_id
 * @property int $qty
 * @property int $amount
 */
class CreditNoteLine extends Model
{
    protected $fillable = [
        'credit_note_id',
        'invoice_line_id',
        'qty',
        'amount',
    ];
}
