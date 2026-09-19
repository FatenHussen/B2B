# docs/

Four kinds of document, each with one owner and one reason to exist. Anything not listed here
was deleted on 2026-09-19 because it repeated one of these or had drifted from the code.

| Directory / file | What it is | Who writes it |
|---|---|---|
| [`api/catalog/`](api/catalog/) | **The contract.** One `ep()` per endpoint: path, method, guard, permission, request, response, errors. Binding for the backend and the five clients. | Edited by hand; a change is a contract change and is said in the PR. |
| [`api/generate.php`](api/generate.php) | Builds `b2b-api.catalog.json`, `b2b-api.openapi.json`, the Postman collection and `docs/api/environments/` from the catalog. | Run, never edited by feature work. |
| [`api/doc08.txt`](api/doc08.txt) | The 170 permission names. Source of a permission *name*; the catalog is the source of a *path*. | Frozen. |
| [`status/`](status/) | **What is live.** Catalog vs `route:list`, route by route, one page per client surface. `00-overview.md` has the totals. | Generated: `php docs/api/generate.php && php docs/status/generate.php`. Never edited. |
| [`plan/`](plan/) | **What is left**, as ordered tickets. `platform-admin.md` for the back office, `apps.md` for the mobile apps and the shared surface. Each ticket names module, tables, permissions, dependencies and acceptance. | Edited by hand when a ticket lands (flip its status) or the catalog changes. |
| [`DocsLast/`](DocsLast/) | **Client briefs.** One spec and one live-routes JSON per client: `platform`, `channel`, `flutter-rep`. What a frontend developer reads. | Regenerated with the catalog. |
| [`adr/`](adr/) | Architecture decisions, numbered, append-only. | One file per decision. |
| [`debt-ledger.md`](debt-ledger.md) | Known, pinned deviations from `CLAUDE.md` — including the Larastan baseline and its per-module pay-down table. | A line is added with its pin and removed in the commit that pays it. |
| [`deploy/current-state.md`](deploy/current-state.md) | The one true description of the production server. | Updated on every deploy change. |

## Working a ticket

1. Find it in `plan/`. Read the catalog entry for every `EP-` it names (`api/catalog/*.php`) —
   that is the request/response contract; the plan file never repeats it.
2. Check `status/` to confirm the route is still ❌ (another branch may have landed it).
3. Build it in the module the ticket names, under the rules in `CLAUDE.md`.
4. Write a test per acceptance line, run the five gates, regenerate `status/`, flip the ticket
   to ✅ with the date.

## What was removed and where its content went

- `backlog/L1..L4` (ticket files) → the L1 tickets are all delivered; their acceptance criteria
  live in the tests. L2–L4 had `contract: proposed` and never matched the catalog; the endpoints
  that do exist in the catalog are re-planned in `plan/`. BE-F11's Larastan analysis is in
  `debt-ledger.md`; BE-F06's out-of-catalog route list is in `debt-ledger.md` and `status/`.
- `sprints/` (sprint specs and frontend task lists) → superseded by `status/` (what is live)
  and `DocsLast/` (what the client consumes).
- `api-contract-status.md`, `permission-gate-inventory.md` → `status/` (generated, per route,
  with the gate column). `api/permission-gate-audit.md` stays: it records the gate *decisions*
  that `app-modules/access/bin/permission-gate-audit.php` and the Access routes cite.
- `business-rules.md`, `conventions.md`, `error-codes.md`, `events.md`, `modules.md` → the parts
  that were rules are in `CLAUDE.md`; the parts that were descriptions of code are the code.
- `deploy/cpanel-deployment.md`, `deploy/vps-deployment.md` → described servers that do not
  exist; `deploy/current-state.md` is the only one.
