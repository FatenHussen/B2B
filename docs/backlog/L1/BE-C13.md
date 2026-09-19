---
id: BE-C13
title: A stored idempotent response belongs to the caller who created it
layer: L1
side: Backend
module: Core
epic: EP-BE-C
sprint: SP-04
points: 3
priority: Highest
contract: internal
endpoints: []
tables: [idempotency_keys]
events: []
blocked_by: [BE-C03]
---

# BE-C13 — A stored idempotent response belongs to the caller who created it

## What is broken, measured

`tests/Feature/Core/IdempotencyKeyScopeTest.php` (9c87575) — two cases, both red on
`main` at b42126b, against the real `PUT /api/v1/admin/channels/{id}`:

| Case | Request | Answer today | Expected |
|---|---|---|---|
| (a) | user A stores a 200 under key K; the same key and body with **no token** | 200, A's stored body, `Idempotent-Replayed: true` | 401 `unauthenticated`, no replay |
| (b) | user A stores a 200 under key K; the same key and body as **user B** | 200, A's stored body, `Idempotent-Replayed: true` | B's own request runs; A's body is never B's answer |

Three facts together produce this:

1. `EnsureIdempotency` is only **appended to the `api` middleware group**, and group
   middleware runs ahead of route middleware. On every guarded route it read and wrote
   `idempotency_keys` while the request was still anonymous — before `auth:platform`
   had asked who was calling.
2. `findLive()` looked a row up by **`key` alone**, and `idempotency_keys.key` was
   globally unique. A key is a client-side UUID; nothing about the row said whose it was.
3. `user_id` was written on create and **read by nothing**.

The body disclosed in the test is small (`{"data":{"id":1}}`); the shape is general —
whatever a write returned to A was returned to whoever presented the key next. The
platform-web team confirmed how keys are made and reused: `crypto.randomUUID()` per user
intent, not per request; the same key is sent again on a retry (network, double click,
after the password-confirm step); `login` and `2fa/verify` carry no key.

A second defect sat beside the first: a row left in `processing` — a worker killed
mid-request, a fatal after the insert — had no expiry but the 24-hour TTL. Every retry
of that intent answered 409 `operation_in_progress` for a day.

## The fix, in three parts

**Order.** `bootstrap/app.php` appends `EnsureIdempotency` to the middleware priority
list after `AuthenticatesRequests` (and after both throttles, should one ever join the
group, so a replay is still a counted request). The group membership is unchanged; the
sorter moves it behind `auth:*` on every route that carries a guard. The exemption check
stays inside the middleware and the eight-path list is untouched — an exempt path has no
guard, so nothing changes for it.

**Scope.** A row is keyed by **(guard, user_id, key)**. `principal()` reads
`$request->user()` after authentication and names the guard that verified it
(`Authenticate` calls `shouldUse()` on success, so `Auth::getDefaultDriver()` is that
guard). The migration drops the unique index on `key` and adds a composite unique on
the triple. Both columns are NOT NULL: MySQL treats NULLs as distinct in a unique index,
and a nullable guard or user would let two anonymous requests with one key both insert.
A caller no guard authenticated is stored as `IdempotencyKey::ANONYMOUS_GUARD` / `_USER_ID`
(`''`, `0`) — every anonymous caller shares that pair, because on a route with no guard
there is nothing to tell them apart. Today no production write reaches that case: every
`api/v1` write without a guard is one of the eight exempt paths (checked against the
route table on 2026-09-17).

**Lock.** `locked_until` is written with every `processing` row
(`core.idempotency_lock_seconds`, default 60 — longer than PHP's default
`max_execution_time`). A retry that finds a `processing` row answers 409 while the lock
holds; once it has lapsed the retry takes the row over with a conditional `UPDATE`
(`WHERE status = 'processing' AND locked_until <= now()`), so two retries racing on an
abandoned row still produce one execution. A completed row holds no lock.

The migration **deletes every existing row** before altering the table. The table is a
24-hour memory, not a record: every row in it was written ahead of authentication, so
none carries the guard it is about to be looked up by, and guessing one would be exactly
the attribution error this ticket ends. A client that replays a key from before the
deploy runs its request once more — what it would have done a day later anyway.

## Requirements

1. On a guarded route, authentication runs before idempotency; a request with no valid
   credential is answered by the guard, never from the table.
2. A stored response is served only to the principal (guard, user) that created it.
3. A key reused by another principal is that principal's first request, whatever the body.
4. The same principal reusing a key with a different body is 409 `idempotency_key_conflict`.
5. A `processing` row answers 409 `operation_in_progress` until its lock lapses, then a
   retry takes it over and runs — once.
6. The eight exempt paths still pass with no token and no key.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._
All in `tests/Feature/Core/IdempotencyKeyScopeTest.php` unless noted.

- [x] (1) the same key and body with no token → 401 `unauthenticated`, no `Idempotent-Replayed`.
- [x] (2) the same key and body as another platform user → B's request runs; two rows under K, one per user.
- [x] (2b) the same key and body as another channel user → the same, on a guard that is not the default one. This is the case that needs the order and not only the scope: before `auth:channel` ran, `$request->user()` asked the default (`platform`) guard, which knows nothing of a channel token, and both channel users would have been the anonymous principal.
- [x] (3) the same key from two users with different bodies → no 409 for the second.
- [x] (4) user 5 on `platform` and user 5 on `channel`, one key → no collision; rows `(channel,5)` and `(platform,5)`.
- [x] (5) a `processing` row whose lock has lapsed → the retry runs and the row completes.
- [x] (6) the eight exempt paths pass with no token and no key (422 from the form request, nothing stored).
- [x] (7) 403 `requires_password_confirm` → confirm → the same key runs once (200) → a fourth call replays the stored 200 and the password hash is untouched.
- [x] (8) the same user, the same key, a different body after a success → 409 `idempotency_key_conflict`.
- [x] (9) a held `processing` row → 409 `operation_in_progress`, handler not run; after `locked_until` → the retry runs.
- [x] `tests/Feature/IdempotencyTest.php` passes whole. One test there — *still demands a key on a write that is not exempt* — sent no token to `POST /app/retailer/register` and got its 400 only because idempotency ran ahead of `auth:app`; it now sends a registration token, and its comment says what it used to pin.
- [x] `tests/Architecture/MigrationTimestampTest.php` passes: the migration takes the minute it was written, `2026_09_17_172100`, shared with no other file.

## Working rules

- `app-modules/core` (middleware, model, migration), `bootstrap/app.php` (priority list),
  `config/core.php` (lock), and the two test files above. Nothing else in code.
- Two documents state the rule and are amended to match: CLAUDE.md rule 9 and
  `docs/adr/ADR-05-idempotency-middleware.md`. Both said "a known key replays"; both now
  say known to whom.
- No route-level workaround, no controller change. The fix is in the middleware or it is
  not the fix.
- The exemption list is a published contract (BE-C03 §5). It does not change here.
- The response shape does not change: the envelope, the error codes and the
  `Idempotent-Replayed` header are as before. No OpenAPI regeneration is owed.

## Status — delivered 2026-09-18 on `work/idempotency-fix`

Five gates run on the worktree database `b2b_platform_test_idem`; results in the pull
request.
