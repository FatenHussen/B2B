<?php

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Tenancy\Database\Factories\SupplyChannelFactory;
use Modules\Tenancy\Domain\Enums\ChannelStatus;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $legal_name
 * @property string|null $tax_number
 * @property string|null $phone
 * @property string|null $email
 * @property ChannelStatus $status
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 */
class SupplyChannel extends Model
{
    /** @use HasFactory<SupplyChannelFactory> */
    use HasFactory, SoftDeletes;

    /**
     * `status` is absent on purpose (rule 8). Until BE-T01 it was here, and
     * `PUT /admin/channels/{id}` could suspend a channel as a side effect of renaming
     * it, with no reason, no actor and no record. It now moves only through
     * `ChannelLifecycle`, which checks the transition and writes the event.
     */
    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'tax_number',
        'phone',
        'email',
        'settings',
    ];

    /**
     * Leaving `status` out of `$fillable` already keeps it from mass assignment; naming
     * it here says why, and survives the day someone adds a column to `$fillable` by
     * copying the list from the migration.
     */
    protected $guarded = ['status'];

    /**
     * The database default is also `active`, but `SupplyChannel::create()` returns an
     * instance that has not been reloaded, and a resource reading `->status->value` on
     * that instance would 500. This makes the default the model's own.
     *
     * Still `active` rather than the catalog's `provisioning`: no provisioning job exists
     * yet (BE-T04, BE-T05), and a channel created into `provisioning` today would have
     * no way out of it. BE-T04 moves creation to `provisioning` when it adds the job.
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChannelStatus::class,
            'settings' => 'array',
        ];
    }

    protected static function newFactory(): SupplyChannelFactory
    {
        return SupplyChannelFactory::new();
    }
}
