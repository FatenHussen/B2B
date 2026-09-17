<?php

declare(strict_types=1);

namespace Modules\Reference\Application\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Events\ReferenceDisabled;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Reference\Domain\Enums\ReferenceEntity;

/**
 * The one way a reference entity is written (BE-R01 requirement 1, BE-R12).
 *
 * Every entity — governorates, zones, activity types, root categories, sale units,
 * equipment, currencies — creates, updates and changes status through here, so the
 * reason, the before/after audit row and the `ReferenceDisabled` event are written once
 * and cannot be forgotten by the seventh controller. There is no delete: disabling is
 * logical (rule 12), and an entity that is still in use refuses to be disabled with
 * `ref_in_use` and the counts that say why.
 */
final class ReferenceMutations
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(ReferenceEntity $entity, array $attributes, ?object $actor, ?string $reason = null): Model
    {
        $class = $entity->modelClass();
        /** @var Model $row */
        $row = $class::query()->create($attributes);
        $row->refresh();

        $this->audit->record(
            'ref.'.$entity->value.'.create',
            $actor,
            $entity->subjectType(),
            (int) $row->getKey(),
            ['after' => $row->getAttributes(), 'reason' => $reason],
        );

        return $row;
    }

    /**
     * Update with a mandatory reason. Only the attributes that actually changed are
     * written to the audit row, before and after, so the log answers "what moved".
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(ReferenceEntity $entity, Model $row, array $attributes, string $reason, ?object $actor): Model
    {
        $before = [];
        $after = [];
        foreach ($attributes as $key => $value) {
            $current = $row->getAttribute($key);
            if ($current instanceof \BackedEnum) {
                $current = $current->value;
            }
            if ($current !== $value) {
                $before[$key] = $current;
                $after[$key] = $value;
            }
        }

        $row->fill($attributes)->save();

        $this->audit->record(
            'ref.'.$entity->value.'.update',
            $actor,
            $entity->subjectType(),
            (int) $row->getKey(),
            ['before' => $before, 'after' => $after, 'reason' => $reason],
        );

        return $row->refresh();
    }

    /**
     * Change status with a reason, refusing to disable what is still in use.
     *
     * `$apply` sets the model's status; the enum differs per entity (zones store
     * `inactive` for the contract's `disabled`), so the caller owns that translation and
     * this method owns everything around it.
     *
     * @param  callable(): void  $apply
     * @param  array<string, int>  $affected  what the change touches, reported back
     * @param  array<string, int>  $inUse  what blocks a disable when any count is > 0
     * @return array{id: int, status: string, affected: array<string, int>}
     */
    public function changeStatus(
        ReferenceEntity $entity,
        Model $row,
        string $fromContract,
        string $toContract,
        callable $apply,
        string $reason,
        ?object $actor,
        array $affected = [],
        array $inUse = [],
    ): array {
        if ($toContract === 'disabled' && array_sum($inUse) > 0) {
            throw DomainException::of(
                ErrorCode::RefInUse,
                __('reference.ref_in_use'),
                ['entity' => $entity->value, 'id' => (int) $row->getKey(), 'affected' => $inUse + $affected],
            );
        }

        $apply();
        $row->save();

        $this->audit->record(
            'ref.'.$entity->value.'.status',
            $actor,
            $entity->subjectType(),
            (int) $row->getKey(),
            [
                'before' => ['status' => $fromContract],
                'after' => ['status' => $toContract],
                'reason' => $reason,
                'affected' => $affected,
            ],
        );

        if ($toContract === 'disabled' && $fromContract !== 'disabled') {
            event(new ReferenceDisabled($entity->value, (int) $row->getKey(), $reason));
        }

        return [
            'id' => (int) $row->getKey(),
            'status' => $toContract,
            'affected' => $affected,
        ];
    }
}
