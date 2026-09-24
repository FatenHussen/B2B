<?php

declare(strict_types=1);

namespace Modules\Promotion\Application\Actions;

use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Promotion\Application\Services\OfferStatusRefresh;
use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Models\Offer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ActivateOffer
{
    public function __construct(
        private readonly RecordsAudit $audit,
        private readonly OfferStatusRefresh $refresh,
    ) {}

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

        $this->refresh->refresh($offer);

        if ($offer->status === OfferStatus::Expired) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('promotion.illegal_transition'));
        }

        if (! in_array($offer->status, [OfferStatus::Stopped, OfferStatus::Draft], true)) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('promotion.illegal_transition'));
        }

        $next = $this->refresh->resolveOnCreate(
            OfferStatus::Active,
            $offer->starts_at?->toIso8601String(),
        );

        $offer->forceFill([
            'status' => $next,
            'stop_reason' => null,
        ])->save();

        $this->audit->record('promotion.offer.activate', $actor, 'offer', (int) $offer->id, [
            'after' => ['reason' => $data['reason'], 'status' => $next->value],
        ], Tenant::currentId());

        return ['status' => $next->value];
    }
}
