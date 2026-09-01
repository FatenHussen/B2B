<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Support\Tenant;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $header = $request->header((string) config('tenancy.header', 'X-Channel-Id'));

        if ($header && $this->canSwitchChannel($user)) {
            Tenant::set((int) $header);

            return $next($request);
        }

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

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        Tenant::forget();
    }

    private function canSwitchChannel(object $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('platform_admin');
    }
}
