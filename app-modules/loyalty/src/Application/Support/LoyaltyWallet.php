<?php

declare(strict_types=1);

namespace Modules\Loyalty\Application\Support;

use Modules\Core\Support\Tenant;
use Modules\Loyalty\Domain\Models\LoyaltyAccount;
use Modules\Loyalty\Domain\Models\LoyaltyRuleSet;
use Modules\Loyalty\Domain\Models\LoyaltyTransaction;

final class LoyaltyWallet
{
    public function account(string $kind, int $ownerId, int $channelId): LoyaltyAccount
    {
        return Tenant::as($channelId, fn () => LoyaltyAccount::query()->firstOrCreate(
            [
                'supply_channel_id' => $channelId,
                'owner_kind' => $kind,
                'owner_id' => $ownerId,
            ],
            ['balance' => 0, 'tier' => 'bronze'],
        ));
    }

    public function credit(
        string $kind,
        int $ownerId,
        int $channelId,
        int $points,
        string $reason,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): LoyaltyAccount {
        return Tenant::as($channelId, function () use ($kind, $ownerId, $channelId, $points, $reason, $referenceType, $referenceId): LoyaltyAccount {
            $account = $this->account($kind, $ownerId, $channelId);
            if ($referenceType !== null && $referenceId !== null) {
                $exists = LoyaltyTransaction::query()
                    ->where('account_id', $account->id)
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->where('type', 'earn')
                    ->exists();
                if ($exists) {
                    return $account;
                }
            }

            $account->balance = (int) $account->balance + $points;
            $account->tier = $this->tierFor($channelId, (int) $account->balance);
            $account->save();

            LoyaltyTransaction::query()->create([
                'supply_channel_id' => $channelId,
                'account_id' => $account->id,
                'type' => 'earn',
                'points' => $points,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            return $account->fresh() ?? $account;
        });
    }

    public function debit(LoyaltyAccount $account, int $points, string $reason, ?int $rewardId = null): void
    {
        $channelId = (int) $account->supply_channel_id;
        Tenant::as($channelId, function () use ($account, $points, $reason, $rewardId, $channelId): void {
            $account->balance = (int) $account->balance - $points;
            $account->tier = $this->tierFor($channelId, (int) $account->balance);
            $account->save();

            LoyaltyTransaction::query()->create([
                'supply_channel_id' => $channelId,
                'account_id' => $account->id,
                'type' => 'redeem',
                'points' => -$points,
                'reason' => $reason,
                'reference_type' => $rewardId === null ? null : 'reward',
                'reference_id' => $rewardId,
            ]);
        });
    }

    /**
     * @return list<array{name: string, threshold: int}>
     */
    public function tiers(int $channelId): array
    {
        return Tenant::as($channelId, function () use ($channelId): array {
            $row = LoyaltyRuleSet::query()->where('supply_channel_id', $channelId)->first();
            $raw = is_array($row?->tiers) ? $row->tiers : [];
            $out = [];
            foreach ($raw as $tier) {
                if (! is_array($tier) || ! isset($tier['name'])) {
                    continue;
                }
                $out[] = [
                    'name' => (string) $tier['name'],
                    'threshold' => (int) ($tier['threshold'] ?? 0),
                ];
            }
            if ($out === []) {
                $out = [
                    ['name' => 'bronze', 'threshold' => 0],
                    ['name' => 'silver', 'threshold' => 1000],
                    ['name' => 'gold', 'threshold' => 5000],
                ];
            }
            usort($out, fn (array $a, array $b): int => $a['threshold'] <=> $b['threshold']);

            return $out;
        });
    }

    public function tierFor(int $channelId, int $balance): string
    {
        $name = 'bronze';
        foreach ($this->tiers($channelId) as $tier) {
            if ($balance >= $tier['threshold']) {
                $name = $tier['name'];
            }
        }

        return $name;
    }

    /**
     * @return array{name: string, remaining: int}|null
     */
    public function nextTier(int $channelId, int $balance): ?array
    {
        foreach ($this->tiers($channelId) as $tier) {
            if ($balance < $tier['threshold']) {
                return ['name' => $tier['name'], 'remaining' => $tier['threshold'] - $balance];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    public function pointsFor(array $rules, string $event, int $total): int
    {
        $matched = false;
        $points = 40;
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $ruleEvent = (string) ($rule['event'] ?? '');
            if ($ruleEvent !== '' && $ruleEvent !== $event) {
                continue;
            }
            $matched = true;
            if (isset($rule['points'])) {
                $points = (int) $rule['points'];
            } elseif (isset($rule['points_per_delivery'])) {
                $points = (int) $rule['points_per_delivery'];
            } elseif (isset($rule['points_per_1000']) && $total > 0) {
                $points = (int) floor($total / 1000) * (int) $rule['points_per_1000'];
            }
        }

        return $matched || $rules === [] ? max(0, $points) : 0;
    }
}
