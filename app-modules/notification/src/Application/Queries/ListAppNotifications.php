<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Notification\Domain\Models\AppNotification;

final class ListAppNotifications
{
    public function __construct(
        private readonly RetailerShoppingContext $shopping,
        private readonly RepSellingContext $selling,
    ) {}

    public function __invoke(object $user, Request $request): LengthAwarePaginator
    {
        $kind = $this->kind($user);
        $read = $request->input('filter.read');

        return AppNotification::query()
            ->where('recipient_kind', $kind)
            ->where('recipient_id', (int) $user->getAuthIdentifier())
            ->whereNull('dismissed_at')
            ->when($read === '0' || $read === 'false', fn ($q) => $q->whereNull('read_at'))
            ->when($read === '1' || $read === 'true', fn ($q) => $q->whereNotNull('read_at'))
            ->orderByDesc('id')
            ->paginate(min((int) $request->input('per_page', 25), 100));
    }

    /**
     * @return array<string, mixed>
     */
    public function map(AppNotification $row): array
    {
        return [
            'id' => (int) $row->id,
            'icon' => $row->icon,
            'title' => $row->title,
            'body' => $row->body,
            'at' => $row->sent_at?->timezone('Asia/Damascus')->toIso8601String()
                ?? $row->created_at?->timezone('Asia/Damascus')->toIso8601String(),
            'read_at' => $row->read_at?->timezone('Asia/Damascus')->toIso8601String(),
            'action' => $row->action,
        ];
    }

    public function kind(object $user): string
    {
        if ($this->shopping->isRetailer($user)) {
            return 'retailer';
        }

        if ($this->selling->isRep($user)) {
            return 'rep';
        }

        return 'rep';
    }
}
