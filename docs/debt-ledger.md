# Debt ledger

Known deviations from the rules in `CLAUDE.md` that are kept on purpose. Each line says what the
debt is, why it stays, what pins it so it cannot grow, and what would retire it. A debt with no
counter is a grave: it gets added to and nobody notices. A line leaves this file in the commit that
pays it.

| Since | Debt | Why it stays | Pinned by | Retired by |
|---|---|---|---|---|
| 2026-09-17 | 16 migrations across 6 stamps share a timestamp (`2026_08_08_125139`, `2026_09_01_100000`, `2026_09_01_120000`, `2026_09_01_130000`, `2026_09_15_120000`, `2026_09_16_130000`). The rule — one unique stamp per file — applies to new files only. | Renaming a migration that has already run makes every deployed database see it as new and try to run it again. | `tests/Architecture/MigrationTimestampTest.php` lists the sixteen by name and fails on any other shared stamp. | Nothing planned. A pair leaves the pin only if a data migration proves the rename safe on every environment. |
| 2026-09-16 | `phpstan-baseline.neon` holds the Larastan level-6 findings that predate the gate. Generated at **585** with no identifier ignored (see the pay-down table below); the pin today is the number in `tests/Architecture/PhpstanBaselineTest.php`. | The gate had never passed before 2026-09-16 (1184 findings, 812 of them configuration). A red gate for weeks is what produced eleven commits saying "phpstan passed" while nobody ran it. | `PhpstanBaselineTest` pins the sum **exactly** — a rise fails, a fall lowers the pin in the same commit; a `<=` ceiling was rejected as silent headroom. A new finding in new code is fixed, never baselined. | One module per commit, highest symptoms-per-declaration first (table below). Retired when the baseline file is empty and the include is removed from `phpstan.neon`. |
| 2026-09-18 | Five live channel routes are outside the API catalog: `GET/POST/DELETE /channel/zones…`, `GET /channel`, `PUT /channel`; plus three unprefixed reference reads (`/governorates`, `/zones`, `/currencies`) under a four-guard `auth:` and eleven `GET /platform/refs/*/{id}` shows. | They serve the dashboard today; the catalog has no `b`/`r`/`e` for them and the unprefixed three break "the prefix names the guard". | `docs/status/generate.php` lists them under "حيّ خارج الكتالوج" on every run. | A catalog ticket that adds them with shapes, or a route change that removes them. Nothing else. |
| 2026-09-19 | `DELETE /platform/channels/{id}` (EP-AD-058) is a direct delete behind `role:platform_admin`, not the catalog's archived-30-days + password + OTP + typed-name + second-approver flow. | The route existed before the flow was specified; removing it would break the back office. | Listed as PA-18 in `docs/plan/platform-admin.md`. | PA-18. |
| 2026-09-19 | `php artisan openapi:generate --check` — the sixth gate — does not exist. The `openapi` package registers no artisan command here; `docs/api/generate.php` produces the OpenAPI document from the catalog by hand. | The catalog is the contract, not the code; a code-derived document would only diff the catalog against itself. | Nothing. | A generator that diffs `route:list` against `b2b-api.openapi.json` — `docs/status/generate.php` already does the path/method/gate half. |

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
