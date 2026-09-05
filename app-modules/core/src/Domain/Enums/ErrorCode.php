<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Enums;

/**
 * The DOC-08 status map, in one place (BE-C02).
 *
 * The code is the contract, the message is for the human. Every code this API is
 * allowed to return is a case here, and its HTTP status is a property of the code
 * rather than a number picked again at each throw site.
 */
enum ErrorCode: string
{
    case Unauthenticated = 'unauthenticated';
    case TokenRevoked = 'token_revoked';

    case WrongGuard = 'wrong_guard';
    case InsufficientPermission = 'insufficient_permission';
    case Requires2fa = 'requires_2fa';
    case RequiresPasswordConfirm = 'requires_password_confirm';
    case SodViolation = 'sod_violation';

    case NotFound = 'not_found';

    case IllegalTransition = 'illegal_transition';
    case OperationInProgress = 'operation_in_progress';
    case StaleVersion = 'stale_version';
    case IdempotencyKeyConflict = 'idempotency_key_conflict';

    case ValidationFailed = 'validation_failed';
    case RefInUse = 'ref_in_use';

    case PlanLimitExceeded = 'plan_limit_exceeded';
    case UpgradeRequired = 'upgrade_required';
    case RateLimited = 'rate_limited';
    case MaintenanceMode = 'maintenance_mode';

    public function status(): int
    {
        return match ($this) {
            self::Unauthenticated, self::TokenRevoked => 401,

            self::WrongGuard,
            self::InsufficientPermission,
            self::Requires2fa,
            self::RequiresPasswordConfirm,
            self::SodViolation => 403,

            // Out-of-tenant access lands here too. Existence is never disclosed.
            self::NotFound => 404,

            self::IllegalTransition,
            self::OperationInProgress,
            self::StaleVersion,
            self::IdempotencyKeyConflict => 409,

            self::ValidationFailed, self::RefInUse => 422,

            self::PlanLimitExceeded => 423,
            self::UpgradeRequired => 426,
            self::RateLimited => 429,
            self::MaintenanceMode => 503,
        };
    }

    public function message(): string
    {
        $line = __('errors.'.$this->value);

        return is_string($line) ? $line : $this->value;
    }
}
