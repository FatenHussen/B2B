<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Models\PasswordConfirmation;
use Modules\Identity\Domain\Models\PlatformUser;
use Symfony\Component\HttpFoundation\Response;

final class RequirePasswordConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('platform');

        if (! $user instanceof PlatformUser) {
            throw new DomainException(__('auth.unauthenticated'), 'unauthenticated', 401);
        }

        $row = PasswordConfirmation::query()
            ->where('platform_user_id', $user->id)
            ->first();

        if ($row === null || ! $row->isValid()) {
            throw new DomainException(__('identity.requires_password_confirm'), 'requires_password_confirm', 403);
        }

        return $next($request);
    }
}
