<?php

declare(strict_types=1);

namespace Modules\Promotion\Application\Actions;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\Tenant;
use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Models\Offer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StopOffer
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array{reason: string}  $data
     * @return array{status: string}
     */
    public function __invoke(object $actor, int $id, array $data): array
    {
        $offer = Offer::query()->find($id);
        if ($offer === null) {
            throw new NotFoundHttpException;
        }

        $offer->forceFill([
            'status' => OfferStatus::Stopped,
            'stop_reason' => $data['reason'],
        ])->save();

        $this->audit->record('promotion.offer.stop', $actor, 'offer', (int) $offer->id, [
            'after' => ['reason' => $data['reason']],
        ], Tenant::currentId());

        return ['status' => OfferStatus::Stopped->value];
    }
}
