<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Support\InvalidFields;
use Modules\Identity\Application\Services\TokenIssuer;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Identity\Domain\Models\RetailerProfileCategory;
use Modules\Identity\Domain\Models\RetailerProfileEquipment;

final class RegisterRetailer
{
    public function __construct(
        private readonly ReferenceDirectory $refs,
        private readonly TokenIssuer $tokens,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function __invoke(AppUser $user, array $data): array
    {
        if (! $this->refs->zoneBelongsToGovernorate((int) $data['zone_id'], (int) $data['governorate_id'])) {
            InvalidFields::throw(['zone_id' => 'identity.zone_not_in_governorate']);
        }

        if (! $this->refs->activityTypeExists((int) $data['activity_type_id'])) {
            InvalidFields::throw(['activity_type_id' => 'identity.activity_type_not_found']);
        }

        if (! $this->refs->allRootCategoriesExist(array_map('intval', $data['category_ids'] ?? []))) {
            InvalidFields::throw(['category_ids' => 'identity.categories_not_found']);
        }

        if (! $this->refs->allEquipmentsExist(array_map('intval', $data['equipment_ids'] ?? []))) {
            InvalidFields::throw(['equipment_ids' => 'identity.equipments_not_found']);
        }

        if ($user->kind !== null && $user->kind !== AppUserKind::Retailer) {
            InvalidFields::throw(['phone' => 'identity.phone_kind_conflict']);
        }

        $profile = DB::transaction(function () use ($user, $data): RetailerProfile {
            $user->forceFill([
                'name' => $data['owner_name'],
                'kind' => AppUserKind::Retailer,
                'status' => UserStatus::Pending,
            ])->save();

            $profile = RetailerProfile::query()->updateOrCreate(
                ['app_user_id' => $user->id],
                [
                    'shop_name' => $data['shop_name'],
                    'activity_type_id' => $data['activity_type_id'],
                    'governorate_id' => $data['governorate_id'],
                    'zone_id' => $data['zone_id'],
                    'lat' => $data['lat'] ?? null,
                    'lng' => $data['lng'] ?? null,
                    'address' => $data['address'] ?? null,
                    'status' => ProfileStatus::PendingReview,
                ],
            );

            $profile->categories()->delete();
            foreach ($data['category_ids'] ?? [] as $categoryId) {
                RetailerProfileCategory::query()->create([
                    'retailer_profile_id' => $profile->id,
                    'root_category_id' => (int) $categoryId,
                ]);
            }

            $profile->equipments()->delete();
            foreach ($data['equipment_ids'] ?? [] as $equipmentId) {
                RetailerProfileEquipment::query()->create([
                    'retailer_profile_id' => $profile->id,
                    'equipment_id' => (int) $equipmentId,
                ]);
            }

            return $profile;
        });

        $user->tokens()->delete();
        $token = $this->tokens->issue($user, ['*']);

        return [
            'retailer' => [
                'id' => $user->id,
                'shop_name' => $profile->shop_name,
                'zone' => ['id' => $profile->zone_id, 'name' => null],
                'activity_type' => ['id' => $profile->activity_type_id, 'name' => null],
                'status' => $profile->status->value,
            ],
            'token' => $token,
        ];
    }
}
