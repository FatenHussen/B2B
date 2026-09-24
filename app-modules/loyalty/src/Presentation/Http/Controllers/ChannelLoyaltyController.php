<?php

declare(strict_types=1);

namespace Modules\Loyalty\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Http\ApiController;
use Modules\Core\Support\Tenant;
use Modules\Loyalty\Domain\Enums\LoyaltyRewardStatus;
use Modules\Loyalty\Domain\Models\LoyaltyReward;
use Modules\Loyalty\Domain\Models\LoyaltyRuleSet;
use Modules\Loyalty\Presentation\Http\Requests\StopLoyaltyRewardRequest;
use Modules\Loyalty\Presentation\Http\Requests\StoreLoyaltyRewardRequest;
use Modules\Loyalty\Presentation\Http\Requests\UpdateLoyaltyRulesRequest;

final class ChannelLoyaltyController extends ApiController
{
    public function rules(): JsonResponse
    {
        $row = LoyaltyRuleSet::query()->first();
        if ($row === null) {
            return $this->ok([
                'retailer_rules' => [],
                'rep_rules' => [],
                'tiers' => [],
            ]);
        }

        return $this->ok([
            'retailer_rules' => $row->retailer_rules,
            'rep_rules' => $row->rep_rules,
            'tiers' => $row->tiers,
        ]);
    }

    public function updateRules(UpdateLoyaltyRulesRequest $request): JsonResponse
    {
        $data = $request->validated();
        LoyaltyRuleSet::query()->updateOrCreate(
            ['supply_channel_id' => Tenant::currentId()],
            [
                'retailer_rules' => $data['retailer_rules'],
                'rep_rules' => $data['rep_rules'],
                'tiers' => $data['tiers'],
            ],
        );

        return $this->ok(['updated' => true]);
    }

    public function rewards(Request $request): JsonResponse
    {
        $page = LoyaltyReward::query()->orderBy('id')->paginate(min((int) $request->input('per_page', 25), 100));

        return $this->paginated($page, fn (LoyaltyReward $row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'points_cost' => (int) $row->points_cost,
            'stock' => (int) $row->stock,
        ]);
    }

    public function storeReward(StoreLoyaltyRewardRequest $request): JsonResponse
    {
        $data = $request->validated();
        $row = LoyaltyReward::query()->create([
            'supply_channel_id' => Tenant::currentId(),
            'name' => $data['name'],
            'points_cost' => (int) $data['points_cost'],
            'stock' => (int) $data['stock'],
            'expires_at' => $data['expires_at'] ?? null,
        ]);
        $row->forceFill(['status' => LoyaltyRewardStatus::Active])->save();

        return $this->ok(['id' => (int) $row->id]);
    }

    public function updateReward(StoreLoyaltyRewardRequest $request, int $id): JsonResponse
    {
        $row = LoyaltyReward::query()->find($id);
        if ($row === null) {
            throw DomainException::of(ErrorCode::NotFound, __('loyalty.not_found'));
        }
        if ($row->status === LoyaltyRewardStatus::Stopped) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('loyalty.illegal_transition'));
        }

        $data = $request->validated();
        $row->fill([
            'name' => $data['name'],
            'points_cost' => (int) $data['points_cost'],
            'stock' => (int) $data['stock'],
            'expires_at' => $data['expires_at'] ?? null,
        ])->save();

        return $this->ok(['id' => (int) $row->id]);
    }

    public function stopReward(StopLoyaltyRewardRequest $request, int $id): JsonResponse
    {
        $row = LoyaltyReward::query()->find($id);
        if ($row === null) {
            throw DomainException::of(ErrorCode::NotFound, __('loyalty.not_found'));
        }
        if ($row->status === LoyaltyRewardStatus::Stopped) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('loyalty.illegal_transition'));
        }

        $data = $request->validated();
        $row->forceFill([
            'status' => LoyaltyRewardStatus::Stopped,
            'stop_reason' => $data['reason'],
        ])->save();

        return $this->ok(['status' => LoyaltyRewardStatus::Stopped->value]);
    }
}
