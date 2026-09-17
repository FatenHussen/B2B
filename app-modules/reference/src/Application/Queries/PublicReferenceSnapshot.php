<?php

declare(strict_types=1);

namespace Modules\Reference\Application\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Enums\ZoneStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Equipment;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Reference\Domain\Models\Zone;

/**
 * BE-R10 — EP-PB-001, the payload every registration screen caches.
 *
 * Six entities, active only, in one response. `since` makes it differential: only rows
 * touched after that moment come back, and `sync_cursor` is the moment to send next
 * time. A row that was disabled after `since` *is* a change — it comes back with its new
 * status so the device drops it — which is why the differential query filters on
 * `updated_at`, not on status, and the full query on both.
 *
 * Channels are never here (REQ-IN-06). Nothing in this class knows a channel exists.
 */
final class PublicReferenceSnapshot
{
    public const CURSOR_PREFIX = 'c_';

    /**
     * @return array<string, mixed>
     */
    public function __invoke(?string $since): array
    {
        $sinceAt = $this->decode($since);
        $now = now();

        return [
            'governorates' => $this->rows(Governorate::query(), $sinceAt, RefStatus::Active->value)
                ->map(fn (Governorate $g) => [
                    'id' => (int) $g->id,
                    'name' => $g->name_ar,
                    'order' => (int) $g->order,
                    'status' => $g->status->value,
                ])->values()->all(),
            'zones' => $this->rows(Zone::query(), $sinceAt, ZoneStatus::Active->value)
                ->map(fn (Zone $z) => [
                    'id' => (int) $z->id,
                    'name' => $z->name,
                    'governorate_id' => (int) $z->governorate_id,
                    'district' => $z->district,
                    'order' => (int) $z->order,
                    'status' => $z->status->toContract(),
                ])->values()->all(),
            'activity_types' => $this->rows(ActivityType::query()->with('suggestedCategories'), $sinceAt, RefStatus::Active->value)
                ->map(fn (ActivityType $a) => [
                    'id' => (int) $a->id,
                    'name' => $a->name,
                    'icon' => $a->icon,
                    'order' => (int) $a->order,
                    'status' => $a->status->value,
                    // BE-R04: the categories a retailer of this activity is shown first.
                    'suggested_category_ids' => $a->suggestedCategories->map(fn (RootCategory $c) => (int) $c->id)->values()->all(),
                ])->values()->all(),
            'root_categories' => $this->rows(RootCategory::query(), $sinceAt, RefStatus::Active->value)
                ->map(fn (RootCategory $c) => [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                    'icon' => $c->icon,
                    'image' => $c->image,
                    'order' => (int) $c->order,
                    'status' => $c->status->value,
                ])->values()->all(),
            'sale_units' => $this->rows(SaleUnit::query(), $sinceAt, RefStatus::Active->value)
                ->map(fn (SaleUnit $u) => [
                    'id' => (int) $u->id,
                    'name' => $u->name,
                    'abbr' => $u->abbr,
                    'default_factor' => (int) $u->default_factor,
                    'status' => $u->status->value,
                ])->values()->all(),
            'equipments' => $this->rows(Equipment::query(), $sinceAt, RefStatus::Active->value)
                ->map(fn (Equipment $e) => [
                    'id' => (int) $e->id,
                    'name' => $e->name,
                    'icon' => $e->icon,
                    'order' => (int) $e->order,
                    'status' => $e->status->value,
                ])->values()->all(),
            'sync_cursor' => $this->encode($now),
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, TModel>
     */
    private function rows(Builder $query, ?Carbon $since, string $activeValue): Collection
    {
        if ($since === null) {
            $query->where('status', $activeValue);
        } else {
            $query->where('updated_at', '>', $since);
        }

        return $query->orderBy('id')->get();
    }

    public function encode(Carbon $at): string
    {
        return self::CURSOR_PREFIX.$at->utc()->format('YmdHis');
    }

    private function decode(?string $cursor): ?Carbon
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }
        $raw = str_starts_with($cursor, self::CURSOR_PREFIX) ? substr($cursor, strlen(self::CURSOR_PREFIX)) : $cursor;
        if (preg_match('/^\d{14}$/', $raw) === 1) {
            return Carbon::createFromFormat('YmdHis', $raw, 'UTC') ?: null;
        }

        // An ISO-8601 `since` is accepted too; anything unreadable means a full snapshot.
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
