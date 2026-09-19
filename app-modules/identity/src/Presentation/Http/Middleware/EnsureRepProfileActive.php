<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Enums\AppUserKind;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\AppUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * A rep whose profile is not `active` reaches no operational surface.
 *
 * The counterpart of {@see EnsureRetailerProfileActive}, attached the same way — to every
 * `api/v1/app/*` route at boot — and stricter: a retailer is held back only while
 * `pending_review`, a rep only ever works while `active`. `rejected` and `disabled` are
 * terminal, and a rep with no profile yet has nothing to work as.
 *
 * Session and logout stay open so the client can show the waiting or refused state and
 * sign out. Registration is behind the registration-scoped token and is not operational.
 */
final class EnsureRepProfileActive
{
    /** @var list<string> */
    private const ALLOWED = [
        'api/v1/app/session',
        'api/v1/app/auth/logout',
        'api/v1/app/rep/register',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof AppUser || $user->kind !== AppUserKind::Rep) {
            return $next($request);
        }

        if (in_array($request->path(), self::ALLOWED, true)) {
            return $next($request);
        }

        if (! $request->is('api/v1/app/*')) {
            return $next($request);
        }

        $status = $user->repProfile?->status;

        if ($status === ProfileStatus::Active) {
            return $next($request);
        }

        throw new DomainException(
            $status === ProfileStatus::PendingReview
                ? __('identity.rep_pending_review')
                : __('identity.rep_not_active'),
            'insufficient_permission',
            403,
        );
    }
}
