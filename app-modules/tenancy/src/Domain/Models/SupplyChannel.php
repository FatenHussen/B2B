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
 * @property string|null $legal_form
 * @property string|null $cr_number
 * @property int|null $logo_media_id
 * @property int|null $plan_id
 * @property string|null $billing_cycle
 * @property Carbon|null $trial_ends_at
 * @property int $custom_discount
 * @property Carbon|null $provisioned_at
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
        'legal_form',
        'cr_number',
        'logo_media_id',
        'plan_id',
        'billing_cycle',
        'trial_ends_at',
        'custom_discount',
        'settings',
    ];

    /**
     * Leaving `status` out of `$fillable` already keeps it from mass assignment; naming
     * it here says why, and survives the day someone adds a column to `$fillable` by
     * copying the list from the migration.
     */
    protected $guarded = ['status'];

    /**
     * `provisioning`, the same as the column default (BE-T04). `SupplyChannel::create()`
     * returns an instance that has not been reloaded, and a resource reading
     * `->status->value` on it would 500 without this; and since `status` is guarded,
     * nothing assigns one at creation — the default *is* the starting state. A channel
     * leaves it only when provisioning finishes (BE-T05), through `ChannelLifecycle`.
     */
    protected $attributes = [
        'status' => 'provisioning',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChannelStatus::class,
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'custom_discount' => 'integer',
            'provisioned_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SupplyChannelFactory
    {
        return SupplyChannelFactory::new();
    }
}
