<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Actions;

use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Support\InvalidFields;
use Modules\Notification\Domain\Models\PushToken;

final class RegisterPushToken
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly RepSellingContext $selling,
    ) {}

    /**
     * @param  array{token: string, platform: string}  $data
     * @return array{success: true}
     */
    public function __invoke(object $user, array $data, ?string $deviceUuid): array
    {
        if ($deviceUuid === null || $deviceUuid === '') {
            InvalidFields::throw(['device_uuid' => 'validation.required']);
        }

        $kind = $this->shopping->isRetailer($user) ? 'retailer' : ($this->selling->isRep($user) ? 'rep' : 'rep');
        $userId = (int) $user->getAuthIdentifier();

        PushToken::query()->updateOrCreate(
            ['device_uuid' => $deviceUuid],
            [
                'recipient_kind' => $kind,
                'recipient_id' => $userId,
                'platform' => $data['platform'],
                'token' => $data['token'],
            ],
        );

        PushToken::query()
            ->where('recipient_kind', $kind)
            ->where('recipient_id', $userId)
            ->where('device_uuid', '!=', $deviceUuid)
            ->delete();

        return ['success' => true];
    }
}
