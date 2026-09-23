<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Actions;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Finance\Application\Jobs\ExportRetailerStatementJob;

final class ExportRetailerStatement
{
    public function __construct(private readonly RetailerShoppingContext $shopping) {}

    /**
     * @param  array{date_from: string, date_to: string, channel_id?: int|null, format: string}  $data
     * @return array{job_id: string}
     */
    public function __invoke(object $user, array $data): array
    {
        if (! $this->shopping->isRetailer($user)) {
            throw DomainException::of(ErrorCode::NotFound, __('finance.not_found'));
        }

        $userId = (int) $user->getAuthIdentifier();
        $key = 'retailer-statement-export:'.$userId;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw DomainException::of(ErrorCode::RateLimited);
        }
        RateLimiter::hit($key, 3600);

        $retailerId = (int) $this->shopping->for($user)['retailer_id'];
        $jobId = 'job_stmt_'.Str::lower((string) Str::ulid());

        ExportRetailerStatementJob::dispatch(
            $jobId,
            $retailerId,
            (string) $data['date_from'],
            (string) $data['date_to'],
            isset($data['channel_id']) ? (int) $data['channel_id'] : null,
            (string) $data['format'],
        )->onQueue('reports');

        return ['job_id' => $jobId];
    }
}
