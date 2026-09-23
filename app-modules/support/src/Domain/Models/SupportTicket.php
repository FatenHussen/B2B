<?php

declare(strict_types=1);

namespace Modules\Support\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'subject', 'body', 'priority', 'requester_type', 'requester_id',
        'resolution', 'root_cause', 'created_by',
    ];

    /** @var list<string> */
    protected $guarded = ['status'];
}
