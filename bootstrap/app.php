<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Http\ApiResponse;
use Modules\Core\Http\AuthFailureCode;
use Modules\Core\Http\Middleware\EnsureIdempotency;
use Modules\Core\Http\Middleware\ResolveTenant;
use Modules\Core\Http\Middleware\SetAcceptLanguage;
use Modules\Identity\Presentation\Http\Middleware\EnforceGuardTokenable;
use Modules\Identity\Presentation\Http\Middleware\EnsureRetailerProfileActive;
use Modules\Identity\Presentation\Http\Middleware\RequireAppKind;
use Modules\Identity\Presentation\Http\Middleware\RequirePasswordConfirmation;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'idempotency' => EnsureIdempotency::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'guard.tokenable' => EnforceGuardTokenable::class,
            'password.confirmed' => RequirePasswordConfirmation::class,
            'app.kind' => RequireAppKind::class,
            'retailer.profile' => EnsureRetailerProfileActive::class,
        ]);

        $middleware->api(remove: [SubstituteBindings::class]);
        $middleware->api(prepend: [
            SetAcceptLanguage::class,
        ]);
        $middleware->api(append: [
            EnsureIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (DomainException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $e->errorCode,
                $e->getMessage(),
                $e->status,
                $e->details,
            );
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $first = collect($e->errors())->flatten()->first();

            return ApiResponse::error(
                'validation_failed',
                is_string($first) && $first !== '' ? $first : __('validation.failed'),
                422,
                $e->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // 401 or 403. `auth:<guard>` cannot tell a foreign token from no token at all,
            // so every cross-guard call reported `unauthenticated` where DOC-08 says
            // `wrong_guard`. This re-reads the presented token and names what happened.
            $code = AuthFailureCode::for($request, $e->guards());

            return ApiResponse::error($code->value, $code->message(), $code->status());
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'insufficient_permission',
                $e->getMessage() !== '' ? $e->getMessage() : __('auth.forbidden'),
                403,
            );
        });

        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // A 403 from the permission middleware names the permission it wanted, so the
            // client can say which grant is missing instead of "forbidden". Registered
            // ahead of the HttpExceptionInterface handler below, which this extends and
            // would otherwise swallow first.
            $required = $e->getRequiredPermissions() ?: $e->getRequiredRoles();
            $first = $required[0] ?? null;

            return ApiResponse::error(
                ErrorCode::InsufficientPermission->value,
                ErrorCode::InsufficientPermission->message(),
                ErrorCode::InsufficientPermission->status(),
                count($required) > 1 ? ['permissions' => array_values($required)] : [],
                is_string($first) ? $first : null,
            );
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $code = match ($e->getStatusCode()) {
                401 => 'unauthenticated',
                403 => 'insufficient_permission',
                404 => 'not_found',
                409 => 'conflict',
                423 => 'plan_limit_exceeded',
                426 => 'upgrade_required',
                429 => 'rate_limited',
                503 => 'maintenance_mode',
                default => 'http_error',
            };

            return ApiResponse::error(
                $code,
                $e->getMessage() !== '' ? $e->getMessage() : $code,
                $e->getStatusCode(),
            );
        });
    })->create();
