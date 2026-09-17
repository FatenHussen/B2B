<?php

declare(strict_types=1);

namespace Modules\Reference\Domain\Enums;

use Illuminate\Database\Eloquent\Model;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\Equipment;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Reference\Domain\Models\Zone;

/**
 * The seven platform reference entities (BE-R01), named as the catalog names them in
 * `/platform/refs/{slug}` and as `ReferenceDisabled` reports them.
 */
enum ReferenceEntity: string
{
    case Governorates = 'governorates';
    case Zones = 'zones';
    case ActivityTypes = 'activity_types';
    case RootCategories = 'root_categories';
    case SaleUnits = 'sale_units';
    case Equipments = 'equipments';
    case Currencies = 'currencies';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Governorates => Governorate::class,
            self::Zones => Zone::class,
            self::ActivityTypes => ActivityType::class,
            self::RootCategories => RootCategory::class,
            self::SaleUnits => SaleUnit::class,
            self::Equipments => Equipment::class,
            self::Currencies => Currency::class,
        };
    }

    /**
     * The `subject_type` written to the audit log: singular, snake_case.
     */
    public function subjectType(): string
    {
        return match ($this) {
            self::Governorates => 'governorate',
            self::Zones => 'zone',
            self::ActivityTypes => 'activity_type',
            self::RootCategories => 'root_category',
            self::SaleUnits => 'sale_unit',
            self::Equipments => 'equipment',
            self::Currencies => 'currency',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $e) => $e->value, self::cases());
    }
}
