<?php

declare(strict_types=1);

namespace Modules\Promotion\Application\Actions;

use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Promotion\Domain\Models\RetailerOfferFavorite;
use Modules\Promotion\Infrastructure\EloquentOfferFeed;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ToggleOfferFavorite
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly EloquentOfferFeed $feed,
    ) {}

    /**
     * @return array{is_favorite: bool}
     */
    public function __invoke(object $user, int $offerId): array
    {
        $ctx = $this->shopping->for($user);
        $visible = $this->feed
            ->matching($ctx['zone_id'], $ctx['activity_type_id'], $ctx['channel_ids'])
            ->whereKey($offerId)
            ->exists();
        if (! $visible) {
            throw new NotFoundHttpException;
        }

        $existing = RetailerOfferFavorite::query()
            ->where('retailer_id', $ctx['retailer_id'])
            ->where('offer_id', $offerId)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return ['is_favorite' => false];
        }

        RetailerOfferFavorite::query()->create([
            'retailer_id' => $ctx['retailer_id'],
            'offer_id' => $offerId,
        ]);

        return ['is_favorite' => true];
    }
}
