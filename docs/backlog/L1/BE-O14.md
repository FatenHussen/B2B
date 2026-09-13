---
id: BE-O14
title: Keep the unit-price freeze separate from the FX-rate freeze
layer: L1
side: Backend
module: Ordering
epic: EP-BE-O
sprint: SP-04
points: 1
priority: Medium
contract: internal
endpoints: []
tables: [sub_orders, sub_order_lines]
events: []
blocked_by: [BE-R09]
---

# BE-O14 — Two freezes, not one

## Background

BE-R09 added `sub_orders.currency_code` and `sub_orders.fx_rate`, written at creation and
never recomputed. They exist so BR-AD-19 — *a rate change never reprices an existing
order* — can be proved rather than assumed: before them, no table held a rate, so a test
that changed an FX rate and re-read an order would have passed while proving nothing.

BE2-PRC05 will add its own freeze, for the unit price against a price list.

## The rule this ticket exists to state

**They are two independent facts about one order, and either can change without the
other.**

| Freeze | Pins | Against | Added by |
|---|---|---|---|
| FX rate | `sub_orders.fx_rate`, `currency_code` | a later row in `fx_rates` | BE-R09 |
| Unit price | its own column, on the line | a later price list | BE2-PRC05 |

A currency can be revalued while a product's list price is untouched, and a price list can
be republished while the rate stands. Collapsing them into one column means one of the two
corrections silently moves the other.

## Requirements

1. BE2-PRC05 adds a **new** column for the unit-price freeze. It does not widen, rename or
   reuse `fx_rate`.
2. It does not remove `currency_code` or `fx_rate`, and does not fold them into a line.
3. If the two ever need to be read together, they are read together — not merged.

## Acceptance criteria

- [ ] After BE2-PRC05, `sub_orders.fx_rate` and `currency_code` still exist and are still
      written at creation.
- [ ] The BR-AD-19 test added in BE-R09 still passes unchanged.
- [ ] A test proves a price-list change does not move a frozen rate, and an FX change does
      not move a frozen unit price.

## Working rules

- Touch only `app-modules/ordering` and its tests.
- Do not modify another module. Use its `Contracts/` or an event.
- Every migration this ticket adds must be backward compatible.
