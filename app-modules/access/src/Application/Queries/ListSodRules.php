<?php

declare(strict_types=1);

namespace Modules\Access\Application\Queries;

use Modules\Access\Domain\Models\SodRule;

final class ListSodRules
{
    /**
     * @return list<array{code: string, permission_a: string, permission_b: string, reason: string, exceptions: array<int, mixed>}>
     */
    public function __invoke(): array
    {
        return SodRule::query()
            ->orderBy('code')
            ->get()
            ->map(fn (SodRule $rule) => [
                'code' => $rule->code,
                'permission_a' => $rule->permission_a,
                'permission_b' => $rule->permission_b,
                'reason' => $rule->reason,
                'exceptions' => $rule->exceptions ?? [],
            ])
            ->all();
    }
}
