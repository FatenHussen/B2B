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
