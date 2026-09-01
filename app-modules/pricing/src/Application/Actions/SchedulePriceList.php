<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Actions;

use Illuminate\Support\Str;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Support\Tenant;
use Modules\Pricing\Application\Jobs\ApplyPriceListScheduleJob;
use Modules\Pricing\Domain\Enums\PriceListStatus;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Pricing\Domain\Models\PriceListSchedule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SchedulePriceList
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array{effective_from: string, changes: list<array{product_id: int, base_price: int}>}  $data
     * @return array{scheduled_job_id: string}
     */
    public function __invoke(object $actor, int $id, array $data): array
    {
        $list = PriceList::query()->find($id);
        if ($list === null) {
            throw new NotFoundHttpException;
        }

        $jobId = 'job_plist_'.Str::lower((string) Str::ulid());
        $when = $data['effective_from'];

        PriceListSchedule::query()->create([
            'price_list_id' => $list->id,
            'effective_from' => $when,
            'payload' => ['changes' => $data['changes']],
            'job_id' => $jobId,
        ]);

        $list->forceFill([
            'status' => PriceListStatus::Scheduled,
            'effective_from' => $when,
        ])->save();

        ApplyPriceListScheduleJob::dispatch($jobId, (int) $list->id)
            ->delay(now()->parse($when))
            ->onQueue('default');

        $this->audit->record('pricing.list.schedule', $actor, 'price_list', (int) $list->id, [
            'after' => ['job_id' => $jobId, 'effective_from' => $when],
        ], Tenant::currentId());

        return ['scheduled_job_id' => $jobId];
    }
}
