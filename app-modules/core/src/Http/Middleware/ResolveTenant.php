<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Support\Tenant;
use Modules\Core\Support\WarehouseScope;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // `X-Channel-Id` is deliberately ignored. A former branch honoured it for
        // `platform_admin` and switched the tenant on every `/platform/*` Tenancy and
        // Reference route that runs this middleware. No documented flow sent it
        // (`docs/DocsLast/platform.md`: do not send), and a switched tenant would have
        // silently filtered any model that moved from exemption to relaxed. Closed BF-07
        // 2026-09-24; pinned by `tests/Feature/Tenancy/PlatformTenantHeaderTest.php`.

        if (isset($user->supply_channel_id) && $user->supply_channel_id) {
            Tenant::set((int) $user->supply_channel_id);

            return $next($request);
        }

        if (method_exists($user, 'defaultChannelId')) {
            $channelId = $user->defaultChannelId();
            if ($channelId !== null) {
                Tenant::set($channelId);
            }
        }

        if (method_exists($user, 'warehouseId')) {
            WarehouseScope::set($user->warehouseId());
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        Tenant::forget();
        WarehouseScope::forget();
    }
}
