---
id: BE-F01
title: Repository and the three environments
layer: L1
side: Backend
module: —
epic: EP-BE-F
sprint: SP-00
points: 5
priority: Highest
contract: internal
endpoints: []
tables: []
events: []
blocked_by: []
---

# BE-F01 — Repository and the three environments

## Requirements

1. Provision three environments. There are three, not four, and none of them is Docker:
   - **Local** — a MySQL 8 running on the host on port **3308**, database `b2b_platform`,
     user `root` with an empty password. Redis optional; the defaults that make it optional
     are `CACHE_STORE=file`, `QUEUE_CONNECTION=database`, `FILESYSTEM_DISK=local`,
     `MAIL_MAILER=log`. On Windows, Horizon needs `pcntl`, so use `queue:work` instead.
   - **Test** — `b2b_platform_test` on the same host MySQL, port 3308, named only in
     `phpunit.xml`. There is no `.env.testing`, so `artisan --env=testing` falls back to
     `.env` and targets the *local* database; only Pest migrates the test database.
     In CI this is a GitHub Actions `services:` MySQL container published on 3308.
   - **Production** — a VPS, provisioned per `docs/deploy/`. Not container-based.
2. Laravel 13 skeleton with app/ kept deliberately thin: Kernel, Providers and Console only.
3. Coding standards: Pint for formatting, Rector for upgrades, Larastan at level 6.
4. All business logic lives under app-modules/, never under app/.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The five gates in CLAUDE.md run on every pull request and on every push to main.
- [ ] Larastan level 6, Pint and Rector all pass with zero findings.
- [ ] app/ contains no controller, model or service.

## Decision — Docker, settled 2026-09-07

**Docker is not used, and the files that implied otherwise have been deleted:**
`docker-compose.yml` and `docker/nginx.conf`.

Four sources disagreed. The evidence settled it rather than the wording:

- `.github/workflows/ci.yml` runs PHP directly on `ubuntu-latest` with a GitHub Actions
  `services:` MySQL. It never invokes Compose.
- No Makefile, no `scripts/`, and no `composer.json` script referenced Docker. `composer dev`
  runs `artisan serve` on the host.
- The compose file contradicted `.env` on the two values that matter: database `b2b` against
  the expected `b2b_platform`, and password `root` against the empty password in use. It could
  not have produced a working environment for this application.
- It bound host port 3308, colliding with the local MySQL that actually serves the project.
- Production is a VPS — see `docs/deploy/` — not a container host.

Requirement 1 previously read "local Docker, test, staging (production-identical) and
production". That was aspiration, never built: no staging environment exists and no pipeline
deploys to one. It has been rewritten to describe what is real. Reinstating staging is a
scope decision, not a documentation fix.

Two documents already stated the conclusion in prose — `README.md` §"Docker is not used" and
`docs/DocsLast/_shared/00-install-the-api.md` §2 — while the files sat in the tree
contradicting them. Those sections are now accurate rather than pre-emptive.

## Working rules

- Touch only `the module named in the front matter` and its tests.
- Do not modify another module. Use its `Contracts/` or an event.
- Do not add a route outside the module `Presentation/Routes/` files.
- JSON only. No Blade, no view, no redirect. PDF document templates belong in `Presentation/Pdf/`.
- Every migration this ticket adds must be backward compatible.

## Definition of done

- [ ] Merged after peer review; Larastan level 6, Pint and Rector pass.
- [ ] Deptrac and the Pest architecture suite pass — no cross-module model import, no float on a money path, no view layer.
- [ ] Unit and feature tests green: 90%+ on engines and money paths, 75%+ on endpoints, permissions and isolation.
- [ ] Every write accepts X-Idempotency-Key unless it is on the documented exemption list.
- [ ] Every channel endpoint has an isolation test proving another channel receives 404.
- [ ] The endpoint declares its permission explicitly and returns the permission key on a 403.
- [ ] The response follows the unified JSON envelope and the HTTP status map; no raw Laravel payload escapes.
- [ ] Every financial, inventory or permission mutation writes an immutable audit entry.
- [ ] The OpenAPI document is regenerated and the typed client still builds.
