<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Models\AppUser;
use Symfony\Component\HttpFoundation\Response;

final class RequireAppKind
{
    public function handle(Request $request, Closure $next, string $kind): Response
    {
        $user = $request->user();

        if (! $user instanceof AppUser) {
            throw new DomainException(__('auth.unauthenticated'), 'unauthenticated', 401);
        }

        $expected = AppUserKind::tryFrom($kind);

        if ($expected === null || $user->kind !== $expected) {
            throw new DomainException(__('auth.forbidden'), 'insufficient_permission', 403);
        }

        return $next($request);
    }
}
