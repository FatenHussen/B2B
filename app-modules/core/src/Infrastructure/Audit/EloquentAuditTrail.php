<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Audit;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Domain\Models\AuditLog;

final class EloquentAuditTrail implements AuditTrail
{
    public function page(array $filters, int $perPage): LengthAwarePaginator
    {
        $paginator = $this->query($filters)->orderByDesc('created_at')->paginate($perPage);

        /** @var LengthAwarePaginator<int, array<string, mixed>> $paginator */
        return $paginator->through(fn (AuditLog $log): array => $this->toArray($log));
    }

    public function stream(array $filters): iterable
    {
        foreach ($this->query($filters)->orderBy('created_at')->cursor() as $log) {
            yield $this->toArray($log);
        }
    }

    public function recent(array $filters, int $limit): array
    {
        return $this->query($filters)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log): array => $this->toArray($log))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    private function query(array $filters): Builder
    {
        $query = AuditLog::query();

        foreach (['actor' => 'actor_id', 'action' => 'action', 'channel_id' => 'channel_id'] as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $query->where($column, $filters[$key]);
            }
        }

        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $like = $filters['action_like'] ?? null;
        $any = $filters['action_any'] ?? [];

        if (($like !== null && $like !== '') || $any !== []) {
            $query->where(function (Builder $q) use ($like, $any): void {
                if ($like !== null && $like !== '') {
                    $q->orWhere('action', 'like', '%'.$like.'%');
                }

                foreach ($any as $action) {
                    $q->orWhere('action', $action);
                }
            });
        }

        return $query;
    }

    /**
     * @return array{at: string|null, actor: int|null, action: string|null, entity_type: string|null, entity_id: int|null, ip: string|null, properties: array<string, mixed>}
     */
    private function toArray(AuditLog $log): array
    {
        $properties = $log->getAttribute('properties');
        $actor = $log->getAttribute('actor_id');
        $subjectId = $log->getAttribute('subject_id');

        return [
            'at' => $log->getAttribute('created_at')?->toIso8601String(),
            'actor' => $actor === null ? null : (int) $actor,
            'action' => $log->getAttribute('action'),
            'entity_type' => $log->getAttribute('subject_type'),
            'entity_id' => $subjectId === null ? null : (int) $subjectId,
            'ip' => $log->getAttribute('ip'),
            'properties' => is_array($properties) ? $properties : [],
        ];
    }
}
