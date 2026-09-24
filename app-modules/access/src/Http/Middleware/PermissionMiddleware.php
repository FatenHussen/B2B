<?php

declare(strict_types=1);

namespace Modules\Access\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Contracts\AccessCatalog;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware as SpatiePermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permission gate that understands both Spatie roles and app-kind grants.
 *
 * Platform / channel / warehouse users carry Spatie `HasRoles` and are handed to
 * Spatie's middleware unchanged. App users have no Spatie roles — their grant is
 * their kind (`rp.*` / `rt.*` via {@see AccessCatalog}). Without this branch every
 * `permission:…` on `/app/*` would throw `missingTraitHasRoles` (BF-09).
 */
final class PermissionMiddleware
{
    public function __construct(
        private readonly SpatiePermissionMiddleware $spatie,
        private readonly AccessCatalog $catalog,
    ) {}

    public function handle(Request $request, Closure $next, string $permission, ?string $guard = null): Response
    {
        $authGuard = Auth::guard($guard);
        $user = $authGuard->user();

        if (! $user) {
            throw UnauthorizedException::notLoggedIn();
        }

        if (isset($user->kind)) {
            $required = array_values(array_filter(explode('|', $permission)));
            $held = $this->catalog->permissionsFor($user);

            foreach ($required as $code) {
                if (in_array($code, $held, true)) {
                    return $next($request);
                }
            }

            throw UnauthorizedException::forPermissions($required);
        }

        return $this->spatie->handle($request, $next, $permission, $guard);
    }
}
