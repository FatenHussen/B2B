<?php

declare(strict_types=1);

namespace Modules\Core\Http;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Domain\Enums\ErrorCode;

/**
 * Names what actually went wrong when authentication failed (BE-C02).
 *
 * `auth:<guard>` cannot tell a foreign token from no token at all: it asks its own
 * provider for a user, gets none, and reports the request unauthenticated. Every
 * cross-guard call therefore answered 401 `unauthenticated` where DOC-08 says 403
 * `wrong_guard`. This re-reads the presented bearer token once, after the guard has
 * already given up, and distinguishes:
 *
 *   - no token, or one that resolves to nothing      → unauthenticated (401)
 *   - a well-formed token whose row is gone, or one
 *     past its expiry                                → token_revoked (401)
 *   - a live token held by another guard's user      → wrong_guard (403)
 *
 * It reaches the four user models through `config('auth')` rather than importing them,
 * because Core may not import another module's Eloquent models.
 */
final class AuthFailureCode
{
    /**
     * @param  array<int, string|null>  $guards  the guards the failing middleware named
     */
    public static function for(Request $request, array $guards): ErrorCode
    {
        $plain = $request->bearerToken();

        if (! is_string($plain) || $plain === '') {
            return ErrorCode::Unauthenticated;
        }

        $model = Sanctum::personalAccessTokenModel();
        $token = $model::findToken($plain);

        if (! $token instanceof PersonalAccessToken) {
            return self::looksRevoked($plain) ? ErrorCode::TokenRevoked : ErrorCode::Unauthenticated;
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return ErrorCode::TokenRevoked;
        }

        $holder = $token->tokenable;

        if ($holder === null) {
            // The token outlived the user it was issued to.
            return ErrorCode::TokenRevoked;
        }

        $owner = self::guardOf($holder);

        if ($owner === null || in_array($owner, self::named($guards), true)) {
            return ErrorCode::Unauthenticated;
        }

        return ErrorCode::WrongGuard;
    }

    /**
     * A Sanctum token is `{id}|{secret}`. If the id is a real number and its row has
     * gone, the token was deleted — a logout or an explicit revoke — which is worth
     * telling the client apart from a token it never had.
     */
    private static function looksRevoked(string $plain): bool
    {
        if (! str_contains($plain, '|')) {
            return false;
        }

        [$id] = explode('|', $plain, 2);

        if (! ctype_digit($id)) {
            return false;
        }

        $model = Sanctum::personalAccessTokenModel();

        return ! $model::query()->whereKey($id)->exists();
    }

    /**
     * The guard whose provider owns this user, or null if no sanctum guard claims it.
     */
    private static function guardOf(object $holder): ?string
    {
        /** @var array<string, array<string, mixed>> $guards */
        $guards = (array) config('auth.guards', []);

        foreach ($guards as $name => $guard) {
            if (($guard['driver'] ?? null) !== 'sanctum') {
                continue;
            }

            $provider = $guard['provider'] ?? null;

            if (! is_string($provider)) {
                continue;
            }

            $model = config('auth.providers.'.$provider.'.model');

            if (is_string($model) && $holder instanceof $model) {
                return (string) $name;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|null>  $guards
     * @return list<string>
     */
    private static function named(array $guards): array
    {
        $named = array_values(array_filter(
            $guards,
            static fn (?string $guard): bool => is_string($guard) && $guard !== '',
        ));

        return $named !== [] ? $named : [(string) config('auth.defaults.guard')];
    }
}
