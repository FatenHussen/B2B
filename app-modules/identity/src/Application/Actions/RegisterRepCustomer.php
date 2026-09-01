<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Support\InvalidFields;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\RepSourcedShopStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepSourcedShop;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Identity\Domain\ValueObjects\PhoneNumber;

final class RegisterRepCustomer
{
    public function __construct(
        private readonly RepSellingContext $selling,
        private readonly ChannelDirectory $channels,
        private readonly ReferenceDirectory $refs,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int, status: string}
     */
    public function __invoke(AppUser $user, array $data): array
    {
        $ctx = $this->selling->for($user);
        $repId = $ctx['rep_id'];
        $channelId = $ctx['channel_ids'][0] ?? 0;
        $opId = (string) $data['client_op_id'];

        $existing = RepSourcedShop::query()
            ->where('rep_id', $repId)
            ->where('client_op_id', $opId)
            ->first();

        if ($existing !== null) {
            return ['id' => (int) $existing->id, 'status' => $existing->status->value];
        }

        $zoneId = (int) $data['zone_id'];
        if (! $this->refs->zoneIsActive($zoneId)) {
            InvalidFields::throw(['zone_id' => 'identity.zone_not_found']);
        }
        if ($channelId < 1 || ! $this->channels->coversZone($channelId, $zoneId)) {
            InvalidFields::throw(['zone_id' => 'identity.zone_outside_coverage']);
        }
        if (! $this->refs->activityTypeExists((int) $data['activity_type_id'])) {
            InvalidFields::throw(['activity_type_id' => 'identity.activity_type_not_found']);
        }

        $govId = $this->refs->zoneGovernorateId($zoneId);
        if ($govId === null) {
            InvalidFields::throw(['zone_id' => 'identity.zone_not_found']);
        }

        $shop = DB::transaction(function () use ($user, $repId, $data, $zoneId, $opId, $govId): RepSourcedShop {
            $placeholder = $this->placeholderUser($data['phone'], $data['owner_name']);
            $retailer = $placeholder->retailerProfile ?? RetailerProfile::query()->create([
                'app_user_id' => $placeholder->id,
                'shop_name' => $data['shop_name'],
                'activity_type_id' => (int) $data['activity_type_id'],
                'governorate_id' => $govId,
                'zone_id' => $zoneId,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'status' => ProfileStatus::PendingReview,
            ]);

            $shop = RepSourcedShop::query()->create([
                'rep_id' => $repId,
                'shop_name' => $data['shop_name'],
                'owner_name' => $data['owner_name'],
                'phone' => PhoneNumber::normalize((string) $data['phone']),
                'zone_id' => $zoneId,
                'activity_type_id' => (int) $data['activity_type_id'],
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'client_op_id' => $opId,
                'status' => RepSourcedShopStatus::PendingSync,
                'retailer_id' => $retailer->id,
            ]);

            $this->audit->record('rep.customer.create', $user, 'rep_sourced_shop', (int) $shop->id, [
                'after' => ['client_op_id' => $opId, 'shop_name' => $data['shop_name']],
            ]);

            return $shop;
        });

        return ['id' => (int) $shop->id, 'status' => $shop->status->value];
    }

    private function placeholderUser(string $phone, string $name): AppUser
    {
        $normalized = PhoneNumber::normalize($phone);
        $existing = AppUser::query()->where('phone', $normalized)->first();
        if ($existing !== null) {
            return $existing;
        }

        return AppUser::query()->create([
            'name' => $name,
            'phone' => $normalized,
            'kind' => null,
            'status' => UserStatus::Pending,
        ]);
    }
}
