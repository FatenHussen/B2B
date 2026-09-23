<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RetailerGroupDirectory;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\RetailerGroup;
use Modules\Identity\Domain\Models\RetailerGroupMember;
use Modules\Identity\Domain\Models\RetailerProfile;

final class SaveRetailerGroup
{
    public function __construct(
        private readonly ChannelDirectory $channels,
        private readonly RetailerGroupDirectory $groups,
        private readonly RecordsAudit $audit,
    ) {}

    /**
     * @param  array{name: string, retailer_ids?: list<int>}  $data
     * @return array{id: int}
     */
    public function create(object $actor, array $data): array
    {
        $ids = $this->validatedMembers($data['retailer_ids'] ?? []);

        $group = DB::transaction(function () use ($data, $ids): RetailerGroup {
            $group = RetailerGroup::query()->create([
                'name' => $data['name'],
            ]);
            $this->syncMembers((int) $group->id, $ids);

            return $group;
        });

        $this->audit->record('identity.retailer_group.create', $actor, 'retailer_group', (int) $group->id, [
            'after' => ['name' => $group->name, 'retailer_ids' => $ids],
        ], Tenant::currentId());

        return ['id' => (int) $group->id];
    }

    /**
     * @param  array{name: string, retailer_ids?: list<int>}  $data
     * @return array{id: int}
     */
    public function update(object $actor, int $id, array $data): array
    {
        $group = RetailerGroup::query()->find($id);
        if ($group === null) {
            throw new DomainException(__('identity.not_found'), 'not_found', 404);
        }

        $ids = $this->validatedMembers($data['retailer_ids'] ?? []);

        DB::transaction(function () use ($group, $data, $ids): void {
            $group->forceFill(['name' => $data['name']])->save();
            $this->syncMembers((int) $group->id, $ids);
        });

        $this->audit->record('identity.retailer_group.update', $actor, 'retailer_group', (int) $group->id, [
            'after' => ['name' => $data['name'], 'retailer_ids' => $ids],
        ], Tenant::currentId());

        return ['id' => (int) $group->id];
    }

    /**
     * @return array{deleted: true}
     */
    public function delete(object $actor, int $id): array
    {
        $group = RetailerGroup::query()->find($id);
        if ($group === null) {
            throw new DomainException(__('identity.not_found'), 'not_found', 404);
        }

        if ($this->groups->isInUse((int) $group->id)) {
            throw DomainException::of(ErrorCode::RefInUse);
        }

        DB::transaction(function () use ($group): void {
            RetailerGroupMember::query()->where('retailer_group_id', $group->id)->delete();
            $group->delete();
        });

        $this->audit->record('identity.retailer_group.delete', $actor, 'retailer_group', $id, [
            'before' => ['name' => $group->name],
        ], Tenant::currentId());

        return ['deleted' => true];
    }

    /**
     * @param  list<int>  $retailerIds
     * @return list<int>
     */
    private function validatedMembers(array $retailerIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $retailerIds)));
        if ($ids === []) {
            return [];
        }

        $zoneIds = $this->channels->zoneIds((int) Tenant::currentId());
        if ($zoneIds === []) {
            InvalidFields::throw(['retailer_ids' => 'identity.retailer_not_in_coverage']);
        }

        $found = RetailerProfile::query()
            ->whereKey($ids)
            ->whereIn('zone_id', $zoneIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($found) !== count($ids)) {
            InvalidFields::throw(['retailer_ids' => 'identity.retailer_not_in_coverage']);
        }

        return $ids;
    }

    /**
     * @param  list<int>  $retailerIds
     */
    private function syncMembers(int $groupId, array $retailerIds): void
    {
        RetailerGroupMember::query()->where('retailer_group_id', $groupId)->delete();
        foreach ($retailerIds as $retailerId) {
            RetailerGroupMember::query()->create([
                'retailer_group_id' => $groupId,
                'retailer_id' => $retailerId,
            ]);
        }
    }
}
