<?php

namespace Modules\Access\Domain\Support;

/**
 * Default roles → permissions (interim catalog until DOC-08's 170
 * SystemPermission values land in SP-02).
 */
final class AccessMatrix
{
    /** @var list<string> */
    public const UNITS = [
        'dashboard', 'catalog', 'pricing', 'promotions', 'orders',
        'inventory', 'reps', 'merchants', 'finance', 'returns', 'content', 'settings',
    ];

    /** @var list<string> */
    public const ACTIONS = ['view', 'create', 'update', 'delete', 'approve'];

    public const EXTRA = [
        'orders.fulfil', 'reps.handover', 'reps.wallets',
        'merchants.financial', 'returns.receive', 'returns.financial',
    ];

    /** @return list<string> */
    public static function permissions(): array
    {
        $names = [];

        foreach (self::UNITS as $unit) {
            foreach (self::ACTIONS as $action) {
                $names[] = "{$unit}.{$action}";
            }
        }

        return array_values(array_unique([...$names, ...self::EXTRA]));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        $full = fn (string $u): array => array_map(fn ($a) => "{$u}.{$a}", self::ACTIONS);
        $view = fn (string $u): array => ["{$u}.view"];
        $create = fn (string $u): array => ["{$u}.view", "{$u}.create"];

        return [
            'platform_admin' => self::permissions(),
            'channel_manager' => self::permissions(),
            'sales_manager' => [
                ...$view('dashboard'),
                ...$view('catalog'),
                ...$view('pricing'),
                ...$create('promotions'),
                ...$full('orders'),
                ...$view('inventory'),
                ...$full('reps'),
                ...$full('merchants'),
                ...$view('finance'),
                'returns.view', 'returns.approve',
                ...$view('content'),
            ],
            'catalog_manager' => [
                ...$view('dashboard'),
                ...$full('catalog'),
                'pricing.view', 'pricing.update',
                ...$create('promotions'),
                ...$view('orders'),
                ...$view('inventory'),
                ...$full('content'),
            ],
            'accountant' => [
                ...$view('dashboard'),
                ...$view('catalog'),
                ...$view('pricing'),
                ...$view('promotions'),
                ...$view('orders'),
                ...$view('inventory'),
                'reps.view', 'reps.wallets',
                'merchants.view', 'merchants.financial',
                ...$full('finance'),
                'returns.view', 'returns.financial',
            ],
            'warehouse_keeper' => [
                ...$view('dashboard'),
                ...$view('catalog'),
                'orders.view', 'orders.fulfil',
                ...$full('inventory'),
                'reps.handover',
                'returns.view', 'returns.receive',
            ],
        ];
    }
}
