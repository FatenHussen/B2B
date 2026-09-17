---
id: BE-R09
title: FX rates
layer: L1
side: Backend
module: Reference
epic: EP-BE-R
sprint: SP-03
points: 5
priority: High
contract: internal
endpoints: []
tables: [fx_rates]
events: []
blocked_by: [BE-R08]
---

# BE-R09 — FX rates

## Requirements

1. rate is an integer; no float exists on this path. Its scale is fixed at 10^6 in `Money`
   and is never a column — see CLAUDE.md rule 7. `fx_rates` already exists with `rate` as
   a `bigInteger` and no scale column.
2. Setting is_display_currency atomically clears the previous display currency.
3. A rate change never reprices an existing order (BR-AD-19) — orders freeze their currency and rate.
4. Add `is_display_currency` as a **new column**. Do not rename `is_base` into it.

## Decision — is_base is not is_display_currency, recorded 2026-09-07

They are different facts and the API exposes only one of them.

- **`is_display_currency`** is what the catalog names in every currency response, and
  requirement 2 makes it switchable through an endpoint.
- **`is_base`** is the unit every stored `bigInteger` amount is denominated in under rule 7.
  It appears in no contract, and is read internally by
  `EloquentReferenceDirectory::baseCurrencyId()`.

Renaming one into the other would hand this ticket's atomic-switch endpoint the power to
change what every stored amount means, with no data migration — the whole ledger silently
reinterpreted. So: two columns, and `is_base` is not writable through the API.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [x] An architecture test proves no float reaches the FX path.
- [x] Two display currencies cannot exist simultaneously, even under concurrent writes.

## Done — 2026-09-17 — EP-AD-040 and EP-AD-043G

A rate is posted **from `currency_id` into the base currency**; the base is implied,
never sent, and a rate for the base against itself is refused. Posting closes the open
rate for the pair at the new `effective_from`, so `FxRateResolver::find()` always has one
answer and the history reads as consecutive intervals. Existing orders are not touched.

`fx_rates.effective_from` moved from TIMESTAMP to DATETIME: MySQL gives the first NOT NULL
TIMESTAMP of a table an implicit `ON UPDATE CURRENT_TIMESTAMP`, and the UPDATE that closes
a rate was silently rewriting the closed rate's start to "now". Same values, no behaviour.
`source` and `entered_by` were added, nullable. Still no scale column (rule 7).

## Working rules

- Touch only `app-modules/reference` and its tests.
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
