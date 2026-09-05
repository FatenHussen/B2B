<?php

declare(strict_types=1);

/*
 * One human message per code in the DOC-08 status map (BE-C02). The code is what a
 * client branches on; this file is what a person reads.
 */

return [
    'unauthenticated' => 'Unauthenticated.',
    'token_revoked' => 'This token is no longer valid. Sign in again.',

    'wrong_guard' => 'This token belongs to a different application.',
    'insufficient_permission' => 'This action is unauthorized.',
    'requires_2fa' => 'Two-factor confirmation is required.',
    'requires_password_confirm' => 'Confirm your password to continue.',
    'sod_violation' => 'This combination breaks a separation-of-duties rule.',

    'not_found' => 'Not found.',

    'illegal_transition' => 'This status change is not allowed.',
    'operation_in_progress' => 'A request with this idempotency key is still running.',
    'stale_version' => 'The record changed while you were editing it. Reload and try again.',
    'idempotency_key_conflict' => 'Idempotency-Key was reused with a different request.',

    'validation_failed' => 'The submitted data is invalid.',
    'ref_in_use' => 'This reference entry is in use and cannot be removed.',

    'plan_limit_exceeded' => 'The current plan limit has been reached.',
    'upgrade_required' => 'A newer client version is required.',
    'rate_limited' => 'Too many requests. Try again shortly.',
    'maintenance_mode' => 'The service is temporarily unavailable for maintenance.',
];
