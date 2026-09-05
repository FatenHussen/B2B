---
id: BE3-FIN04
title: Rep wallet
layer: L3
side: Backend
module: Finance
epic: EP-L3-FIN
sprint: SP-13
points: 8
priority: Highest
contract: proposed
endpoints: []
tables: [rep_wallets, wallet_transactions]
events: []
blocked_by: [BE3-FIN02]
---

# BE3-FIN04 — Rep wallet

## Requirements

1. The wallet equals collections minus amounts handed to the accountant, at any instant (AC-06).
2. A withdrawal carries an amount and an operation number obtained from the accountant.
3. A cash cap above which handover becomes mandatory, enforced by the server (BR-12).
4. Every wallet movement is a ledger entry; the balance is never stored as a standalone editable number.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] The wallet balance is recomputable from its transactions and always matches.
- [ ] Exceeding the cash cap blocks further collection until settlement.

## Working rules

- Touch only `app-modules/finance` and its tests.
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
