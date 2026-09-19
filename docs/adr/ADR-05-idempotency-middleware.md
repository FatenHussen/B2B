# ADR-05 — Idempotency at the middleware

**Status:** Accepted  
**Sprint:** SP-00  
**Source:** DOC-10 §5.4, BR-AD-24, REQ-CM-012

## Decision

Write endpoints honour `X-Idempotency-Key` (and the legacy `Idempotency-Key`
header) via `EnsureIdempotency`. Replay, in-progress conflict, and payload
mismatch are handled once, not in every controller.

## Behaviour

1. Key complete → replay stored response.
2. Key in progress → 409 `operation_in_progress`.
3. New key → run, store 24 hours.
4. Same key, different body → 409 `idempotency_key_conflict`.

## Amended 2026-09-17 (BE-C13)

A key is known **to one caller**. The row is keyed by (guard, user_id, key) and the
middleware runs after `auth:*` — via the middleware priority list, not the group order —
so a stored response answers only the principal that created it; the same key from
another user, or from no user, is that caller's own first request. A row in progress is
held for `core.idempotency_lock_seconds`; past that, a retry takes it over and runs.
Before BE-C13 the row was found by key alone, ahead of authentication, and whoever
presented a key next was handed the response the platform had computed for someone else.
