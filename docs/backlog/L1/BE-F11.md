---
id: BE-F11
title: The Larastan gate has never passed — make it passable, or take it off the list
layer: L1
side: Backend
module: —
epic: EP-BE-F
sprint: SP-04
points: 3
priority: High
contract: internal
endpoints: []
tables: []
events: []
blocked_by: []
---

# BE-F11 — The Larastan gate has never passed

## The fact

CLAUDE.md names `./vendor/bin/phpstan analyse` as one of five gates that "must pass", and
`.github/workflows/ci.yml` runs it on every pull request and push to main. It has never
passed. `e89279f`'s commit message reads "phpstan 228 before and after"; the count has
only grown since. Measured 2026-09-16 on `work/be-c12` at `a154fa3`, from the analyser's
own JSON (`-v`, not the truncated summary):

| | |
|---|---|
| Findings | **1184** in 191 files |
| In `tests/` | 605 |
| `property.notFound` | 495 |
| `method.notFound` | 453 |
| Messages naming Pest's `TestCall` / `HigherOrderTapProxy` | 494 |
| `missingType.iterableValue` | 86 |
| `argument.templateType` | 65 |
| In files unchanged since `e89279f` | 587 (131 files) |

A gate nobody can pass is a gate nobody runs. It is either made passable or removed
from the five; a line in CLAUDE.md that CI contradicts on every run teaches the reader
to ignore CLAUDE.md.

## Two structural causes, not a thousand defects

Both are configuration, and both were verified by re-measuring with a temporary
config — no code touched:

1. **The analyser does not know the columns.** Larastan learns model attributes by
   reading migrations from `databaseMigrationsPath`, which is empty here, while
   `excludePaths` shuts out `database/migrations` and all eighteen
   `app-modules/*/database` folders. So every `$product->sku` is an undefined property.
2. **The analyser does not know who `$this` is in a Pest closure.** Pest's own docblocks
   declare `@param-closure-this TestCall` on `it()`, `test()` and `beforeEach()`; at run
   time Pest binds the closure to `Tests\TestCase`. So every `$this->postJson()` in every
   test is a call to an undefined method on `TestCall`.

## What the configuration alone recovers (measured)

| Step | Findings | What moved |
|---|---|---|
| As is | 1184 | — |
| + `databaseMigrationsPath`: root + the 18 module migration dirs | 890 | `property.notFound` 495 → 194 |
| + Pest's `extension.neon` and `phpstan-pest-extension.neon` | 881 | TapProxy property access |
| + a stub redeclaring `it/test/beforeEach/afterEach` with `@param-closure-this \Tests\TestCase` | **435** | `method.notFound` 453 → 23; Pest-`$this` messages 494 → 18 |
| + `tests` removed from `paths` (code only, for the record) | 325 | — |

The stub is twenty lines. Nothing above suppresses a finding; it tells the analyser two
true things it did not know.

## What is left after that — 435, of which 325 in code

| Identifier | Count | Nature |
|---|---|---|
| `property.notFound` | 180 | almost all on the bare `Illuminate\Database\Eloquent\Model` — relations and builders declared without their generic type, so a `->first()` or `->media` is a Model with no columns. The `missingType.generics` ignore in `phpstan.neon` hides the cause of these while leaving the symptom |
| `missingType.iterableValue` | 86 | `array` parameters and returns with no value type — docblocks |
| `argument.templateType` | 52 | same family: generics not stated |
| `method.notFound` | 23 | real, e.g. `LengthAwarePaginator::setCollection()` on the contract type |
| `property.nonObject`, `argument.type`, `assign.propertyType`, `method.nonObject` | ~50 | real, per site |

These are code, spread over Fulfillment (17 iterable alone), Ordering, Delivery,
Catalog, Pricing, Returns — not one module's, and not one afternoon's.

## Requirements

1. **Configuration first, in one commit, before any code line moves:**
   `databaseMigrationsPath` naming the root and module migration directories; Pest's two
   neon files included; the `@param-closure-this \Tests\TestCase` stub under
   `stubFiles`; `missingType.generics` no longer ignored (it hides the cause of the
   biggest remaining bucket). Re-measure and record the number in this ticket.
2. **Then decide, with that number in hand**, one of:
   - **(A) Baseline and shrink.** Generate `phpstan-baseline.neon` at that point so the
     gate is green today and fails only on *new* findings; pin the baseline's count in an
     architecture test that allows it to fall and never rise (the escape-inventory
     pattern). The debt is known, not discovered, and every module pass shrinks it.
   - **(B) Pay it down first**, one module per commit, then turn the gate on. Honest but
     the gate stays red for the duration, which is the situation this ticket exists to end.
   - **(C) Remove the line** from CLAUDE.md and `ci.yml`. Only if (A) is refused.
3. CLAUDE.md's "five gates" paragraph says which of the three was chosen and what the
   number is.

## Acceptance criteria

_Write one test per criterion. Name the test after the criterion._

- [ ] `./vendor/bin/phpstan analyse` exits 0 on `main`, in CI, on the commit that closes
      this ticket — or the gate is no longer listed anywhere as one that must pass.
- [ ] The configuration commit alone takes the count from 1184 to under 450 with no
      code change, and the commit message records both numbers.
- [ ] If (A): a pinned count that a later commit can only lower.

## Working rules

- The configuration commit touches `phpstan.neon`, a stub file, `ci.yml` and CLAUDE.md
  only.
- No `@phpstan-ignore` and no inline `@var` to make a site quiet; a finding is either
  fixed, baselined under (A), or stays.
- The count in this ticket is the truth as of the date above; whoever re-measures
  replaces it, with the date.
