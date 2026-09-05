# Error codes

The `code` is the contract. The `message` is for the human.

| HTTP | Internal codes | When | Owning ticket |
|---|---|---|---|
| 401 | `unauthenticated, token_revoked` | Authentication failed or the token was revoked | BE-C02 / BE-I09 |
| 403 | `wrong_guard, insufficient_permission, requires_2fa, requires_password_confirm, sod_violation` | Guard or permission failure, or a step-up challenge | BE-C02 / BE-A16 / BE-I17 |
| 404 | `not_found` | Outside the tenant scope, or genuinely absent — existence is never disclosed | BE-T02 |
| 409 | `illegal_transition, operation_in_progress, stale_version, idempotency_key_conflict, conflict` | State conflict or an operation already in flight | BE-C03 / BE-C07 |
| 422 | `validation_failed, ref_in_use` | Input validation, keyed by field in details | BE-C02 |
| 423 | `plan_limit_exceeded` | The channel exceeded a plan limit | BE-T12 |
| 426 | `upgrade_required` | The app version is below the supported minimum | BE-C02 |
| 429 | `rate_limited` | Rate limit exceeded; Retry-After is set | BE-C08 |
| 503 | `maintenance_mode` | Planned maintenance | BE-C02 |
