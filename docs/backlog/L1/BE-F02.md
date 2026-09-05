---
id: BE-F02
title: Module scaffold with Composer path repositories
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
blocked_by: [BE-F01]
---

# BE-F02 — Module scaffold with Composer path repositories

## Requirements

1. Create app-modules/ with a local Composer path repository per module (b2b/core, b2b/identity, …).
2. Each module owns src/Contracts, Domain, Application, Infrastructure, Presentation, Database, Policies, config, lang, tests.
3. A module declares its dependencies in its own composer.json; a dependency it does not declare is not autoloadable.
4. Publish a module template so a new module is created identically every time.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] Importing a class from an undeclared module fails at autoload, not at review.
- [ ] The six Layer 1 modules exist and boot.
- [ ] A new module can be scaffolded from the template in one command.

## Out of scope

- nwidart/laravel-modules or any dynamic module loader — explicitly rejected in DOC-10 §1.3.

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
