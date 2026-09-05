<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Http\ApiResponse;
use Modules\Core\Http\Middleware\EnsureIdempotency;
use Modules\Core\Http\Middleware\ResolveTenant;
use Modules\Core\Http\Middleware\SetAcceptLanguage;
use Modules\Identity\Presentation\Http\Middleware\EnforceGuardTokenable;
use Modules\Identity\Presentation\Http\Middleware\RequireAppKind;
use Modules\Identity\Presentation\Http\Middleware\RequirePasswordConfirmation;
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

            return ApiResponse::error('unauthenticated', __('auth.unauthenticated'), 401);
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

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $code = match ($e->getStatusCode()) {
                401 => 'unauthenticated',
                403 => 'insufficient_permission',
                404 => 'not_found',
                409 => 'conflict',
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
