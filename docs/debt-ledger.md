# Debt ledger

Known deviations from the rules in `CLAUDE.md` that are kept on purpose. Each line says what the
debt is, why it stays, what pins it so it cannot grow, and what would retire it. A debt with no
counter is a grave: it gets added to and nobody notices. A line leaves this file in the commit that
pays it.

| Since | Debt | Why it stays | Pinned by | Retired by |
|---|---|---|---|---|
| 2026-09-16 | `phpstan-baseline.neon` holds the Larastan level-6 findings that predate the gate. Generated at **585** with no identifier ignored (see the pay-down table below); the pin today is the number in `tests/Architecture/PhpstanBaselineTest.php`. | The gate had never passed before 2026-09-16 (1184 findings, 812 of them configuration). A red gate for weeks is what produced eleven commits saying "phpstan passed" while nobody ran it. | `PhpstanBaselineTest` pins the sum **exactly** — a rise fails, a fall lowers the pin in the same commit; a `<=` ceiling was rejected as silent headroom. A new finding in new code is fixed, never baselined. | One module per commit, highest symptoms-per-declaration first (table below). Retired when the baseline file is empty and the include is removed from `phpstan.neon`. |
| 2026-09-17 | 16 migrations across 6 stamps share a timestamp (`2026_08_08_125139`, `2026_09_01_100000`, `2026_09_01_120000`, `2026_09_01_130000`, `2026_09_15_120000`, `2026_09_16_130000`). The rule — one unique stamp per file — applies to new files only. | Renaming a migration that has already run makes every deployed database see it as new and try to run it again. | `tests/Architecture/MigrationTimestampTest.php` lists the sixteen by name and fails on any other shared stamp. | Nothing planned. A pair leaves the pin only if a data migration proves the rename safe on every environment. |
| 2026-09-18 | ~~Five live channel routes outside the catalog~~ **Retired 2026-09-23** (zones + own channel settings catalogued). Shared unprefixed refs (`/governorates`, `/zones`, `/currencies`) and platform ref shows catalogued 2026-09-24. | — | — | Done. |
| 2026-09-19 | `DELETE /platform/channels/{id}` (EP-AD-058) is a direct delete behind `role:platform_admin`, not the catalog's archived-30-days + password + OTP + typed-name + second-approver flow. | The route existed before the flow was specified; removing it would break the back office. | Listed as PA-18 in `docs/plan/platform-admin.md`. | PA-18. |
| 2026-09-24 | `ResolveTenant`'s first branch honours `X-Channel-Id` for any user with role `platform_admin` and sets the tenant to it, on every Tenancy and Reference route under `/platform/*` (`'tenant'` in their route groups). No documented flow sends it — `docs/DocsLast/platform.md` says "do not send" and the Postman generator disables it for the platform audience — but the code accepts it, nothing pins that, and it decides two channel-scope verdicts: `FeatureFlagOverride` and `ChannelEvent` are **exempt** from the scope rather than relaxed because a relaxed filter under a switched tenant would silently hide rows from the back office (`tests/Architecture/ChannelScopeTest.php`). | Closing it is a behaviour change on the platform guard outside any ticket; the branch predates the back-office tickets that now sit behind it. | Nothing. The two exemption comments name this row as the condition under which they become wrong. | Close the branch, or bind it to a documented tenant-switch flow with a test — and in the same commit re-evaluate `FeatureFlagOverride` and `ChannelEvent` towards relaxed, since the reason for exempting them leaves with it. |
| 2026-09-19 | `php artisan openapi:generate --check` — the sixth gate — does not exist. The `openapi` package registers no artisan command here; `docs/api/generate.php` produces the OpenAPI document from the catalog by hand. | The catalog is the contract, not the code; a code-derived document would only diff the catalog against itself. | Nothing. | A generator that diffs `route:list` against `b2b-api.openapi.json` — `docs/status/generate.php` already does the path/method/gate half. |
| 2026-09-19 | The rep session (`GET /app/session`, `AppSession`) does not return the profile status or the channel. A disabled or still-pending rep receives 403 `insufficient_permission` from `EnsureRepProfileActive` on every `/app/*` route and cannot tell why from the session it can still read. | The session shape is a published contract consumed by the Flutter rep app; adding fields is a contract change to say out loud, not to slip into an ownership fix. | Nothing yet. `tests/Feature/Identity/RepProfileStatusTest.php` pins the 403 and its message; nothing pins that the session stays silent. | Add `status` and `channel` to the rep session payload, regenerate OpenAPI, and say so in the pull request. |
| 2026-09-19 | OTP was off on `api.sentraxsy.com`: `APP_ENV=staging` with `OTP_BYPASS=true` on a production domain (`docs/deploy/current-state.md`). **Code fix 2026-09-24:** `OtpService::bypassed()` honours the flag only under `local`/`testing`, so staging no longer accepts every code. Server still should set `OTP_BYPASS=false` and prefer `APP_ENV=production`. | Residual ops debt on the host `.env` only. | `tests/Feature/Identity/OtpBypassTest.php` proves bypass is dead under `production` and `staging`. | Confirm server `.env` has `OTP_BYPASS=false` (and ideally `APP_ENV=production`) before the first real user; then remove this row. |
| 2026-09-24 | Channel/warehouse DOC features without catalog EPs (FEFO lots, offline WH, batch pick, live GPS map, returns SLA, zone polygons, retailer 360 on channel, credit approval queue). | Launch completeness is catalog-complete; these are DOC depth for v1.1. | `docs/plan/channel-warehouse-v1.1.md` lists reserved EP ids and priority. DocsLast §8 names them. | Add catalog EP then implement, one capability per ticket. |

## Larastan pay-down, per module

From the baseline as generated on 2026-09-16 (branch `work/be-f11`, no identifier ignored). "Root
declarations" are relations declared without their generic type (`HasMany<Related, $this>`); the
"symptoms" are the bare-`Model` property accesses that fall with them, which is why one small
commit per row is the unit of work, highest symptoms-per-declaration first. `core`'s 110 are
contract and support signatures with no symptom attached; `tests`' 104 are Pest residue the
configuration cannot express. Nothing in the baseline is a runtime defect.

| Module | Baseline findings | Root declarations | Symptoms that fall with them | Symptoms per declaration |
|---|---|---|---|---|
| `ordering` | 121 | 16 | 83 | 5.2 |
| `delivery` | 29 | 3 | 14 | 4.7 |
| `fulfillment` | 52 | 7 | 28 | 4 |
| `promotion` | 32 | 9 | 19 | 2.1 |
| `returns` | 10 | 2 | 3 | 1.5 |
| `catalog` | 59 | 25 | 19 | 0.8 |
| `identity` | 30 | 20 | 6 | 0.3 |
| `pricing` | 14 | 5 | 1 | 0.2 |
| `core` | 113 | 110 | 0 | 0 |
| `finance` | 1 | 1 | 0 | 0 |
| `inventory` | 6 | 3 | 0 | 0 |
| `reference` | 6 | 6 | 0 | 0 |
| `tests` | 104 | 6 | 0 | 0 |
| `access` | 3 | 0 | 0 | — |
| `tenancy` | 5 | 0 | 0 | — |
| **total** | **585** | **213** | **173** | |
