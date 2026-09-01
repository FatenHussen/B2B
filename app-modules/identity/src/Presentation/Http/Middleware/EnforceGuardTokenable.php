<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Models\WarehouseUser;
use Symfony\Component\HttpFoundation\Response;

final class EnforceGuardTokenable
{
    public function handle(Request $request, Closure $next, string $guard): Response
    {
        $expected = match ($guard) {
            'platform' => PlatformUser::class,
            'channel' => ChannelUser::class,
            'warehouse' => WarehouseUser::class,
            'app' => AppUser::class,
            default => throw new \InvalidArgumentException("Unknown API guard [{$guard}]."),
        };

        if (! $request->user() instanceof $expected) {
            throw new AuthenticationException('Unauthenticated.', [$guard]);
        }

        return $next($request);
    }
}
