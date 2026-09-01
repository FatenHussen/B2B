<?php

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only audit row. Application code must never update or delete.
 * MySQL triggers enforce the same rule at the database.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'actor_type',
        'action',
        'subject_type',
        'subject_id',
        'channel_id',
        'properties',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('audit_logs is append-only (AC-AD-08).');
        });

        static::deleting(function () {
            throw new LogicException('audit_logs is append-only (AC-AD-08).');
        });
    }
}
