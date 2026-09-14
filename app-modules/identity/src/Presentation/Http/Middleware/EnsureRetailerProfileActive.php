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
 * BE-I06: a retailer in `pending_review` reaches no operational surface.
 *
 * Session and logout stay open so the client can show the waiting state and sign out.
 * Registration itself is behind the registration-scoped token and is not operational.
 */
final class EnsureRetailerProfileActive
{
    /** @var list<string> */
    private const ALLOWED = [
        'api/v1/app/session',
        'api/v1/app/auth/logout',
        'api/v1/app/retailer/register',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof AppUser || $user->kind !== AppUserKind::Retailer) {
            return $next($request);
        }

        if (in_array($request->path(), self::ALLOWED, true)) {
            return $next($request);
        }

        if (! $request->is('api/v1/app/*')) {
            return $next($request);
        }

        $status = $user->retailerProfile?->status;

        if ($status === ProfileStatus::PendingReview) {
            throw new DomainException(
                __('identity.retailer_pending_review'),
                'insufficient_permission',
                403,
            );
        }

        return $next($request);
    }
}
