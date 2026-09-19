<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\RepSourcedShopStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RepSourcedShop;
use Modules\Identity\Domain\Models\RetailerProfile;

final class DecideRepSourcedShop
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array{decision: string, reason?: string|null}  $data
     * @return array{id: int, status: string}
     */
    public function __invoke(object $actor, int $shopId, array $data): array
    {
        $channelId = (int) Tenant::currentId();
        $shop = RepSourcedShop::query()->whereKey($shopId)->first();
        if ($shop === null) {
            throw DomainException::of(ErrorCode::NotFound, __('identity.not_found'));
        }

        $profile = RepProfile::query()
            ->whereKey($shop->rep_id)
            ->where('channel_id', $channelId)
            ->first();
        if ($profile === null) {
            throw DomainException::of(ErrorCode::NotFound, __('identity.not_found'));
        }

        if ($shop->status !== RepSourcedShopStatus::PendingSync) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('identity.illegal_sourced_shop_transition'));
        }

        $approve = $data['decision'] === 'approve';

        return DB::transaction(function () use ($actor, $shop, $approve, $data, $channelId): array {
            $status = $approve ? RepSourcedShopStatus::Linked : RepSourcedShopStatus::Rejected;
            $shop->status = $status;
            $shop->save();

            if ($shop->retailer_id !== null) {
                $retailer = RetailerProfile::query()->whereKey($shop->retailer_id)->first();
                if ($retailer !== null) {
                    $retailer->status = $approve ? ProfileStatus::Active : ProfileStatus::Rejected;
                    $retailer->save();

                    $owner = AppUser::query()->whereKey($retailer->app_user_id)->first();
                    if ($owner !== null) {
                        $owner->forceFill([
                            'status' => $approve ? UserStatus::Active : UserStatus::Suspended,
                            'kind' => $approve ? AppUserKind::Retailer : $owner->kind,
                        ])->save();
                    }
                }
            }

            $this->audit->record('rep.sourced_shop.decide', $actor, 'rep_sourced_shop', (int) $shop->id, [
                'after' => [
                    'decision' => $data['decision'],
                    'status' => $status->value,
                    'reason' => $data['reason'] ?? null,
                ],
            ], $channelId);

            return ['id' => (int) $shop->id, 'status' => $status->value];
        });
    }
}
