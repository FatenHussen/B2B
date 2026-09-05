<?php

namespace Modules\Tenancy\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Tenancy\Database\Factories\SupplyChannelFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $legal_name
 * @property string|null $tax_number
 * @property string|null $phone
 * @property string|null $email
 * @property string $status
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 */
class SupplyChannel extends Model
{
    /** @use HasFactory<SupplyChannelFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'tax_number',
        'phone',
        'email',
        'status',
        'settings',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected static function newFactory(): SupplyChannelFactory
    {
        return SupplyChannelFactory::new();
    }
}
