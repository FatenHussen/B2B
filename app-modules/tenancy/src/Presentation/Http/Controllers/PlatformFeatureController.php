<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\FeatureFlags;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Http\ApiController;
use Modules\Tenancy\Domain\Models\FeatureFlag;
use Modules\Tenancy\Domain\Models\FeatureFlagOverride;
use Modules\Tenancy\Domain\Models\SupplyChannel;

final class PlatformFeatureController extends ApiController
{
    public function index(): JsonResponse
    {
        $rows = FeatureFlag::query()->orderBy('key')->get()->map(fn (FeatureFlag $f) => [
            'key' => $f->key,
            'description' => $f->description,
            'enabled_globally' => $f->enabled_globally,
            'rollout_percent' => $f->rollout_percent,
            'channel_ids' => FeatureFlagOverride::query()->where('feature_key', $f->key)->pluck('channel_id')->all(),
            'scopes' => $f->scopes ?? [],
            'planned_removal_at' => $f->planned_removal_at?->toIso8601String(),
        ]);

        return $this->ok($rows->all());
    }

    public function store(Request $request, RecordsAudit $audit): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:feature_flags,key'],
            'description' => ['sometimes', 'string'],
            'enabled_globally' => ['sometimes', 'boolean'],
            'rollout_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'scopes' => ['sometimes', 'array'],
        ]);

        $flag = FeatureFlag::query()->create([
            'key' => $data['key'],
            'description' => $data['description'] ?? null,
            'enabled_globally' => $data['enabled_globally'] ?? false,
            'rollout_percent' => $data['rollout_percent'] ?? 100,
            'scopes' => $data['scopes'] ?? [],
        ]);

        $audit->record('feature.created', $request->user(), FeatureFlag::class, (int) $flag->id, ['key' => $flag->key]);

        return $this->created(['key' => $flag->key]);
    }

    public function override(Request $request, string $key, RecordsAudit $audit): JsonResponse
    {
        $data = $request->validate([
            'channel_id' => ['required', 'integer', 'exists:supply_channels,id'],
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3'],
        ]);

        FeatureFlag::query()->where('key', $key)->firstOrFail();

        $row = FeatureFlagOverride::query()->updateOrCreate(
            ['feature_key' => $key, 'channel_id' => (int) $data['channel_id']],
            [
                'enabled' => (bool) $data['enabled'],
                'reason' => $data['reason'],
                'actor_id' => $request->user()?->getAuthIdentifier(),
            ],
        );

        $audit->record('feature.override', $request->user(), FeatureFlagOverride::class, (int) $row->id, $data);

        return $this->ok(['key' => $key, 'channel_id' => (int) $data['channel_id'], 'enabled' => (bool) $data['enabled']]);
    }

    public function clearOverride(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'channel_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3'],
        ]);

        FeatureFlagOverride::query()
            ->where('feature_key', $key)
            ->where('channel_id', (int) $data['channel_id'])
            ->delete();

        return $this->ok(['key' => $key, 'channel_id' => (int) $data['channel_id']]);
    }

    public function forChannel(SupplyChannel $supplyChannel, FeatureFlags $flags): JsonResponse
    {
        $map = $flags->forChannel((int) $supplyChannel->id);
        $list = [];
        foreach ($map as $key => $enabled) {
            $list[] = ['key' => $key, 'enabled' => $enabled];
        }

        return $this->ok($list);
    }
}
