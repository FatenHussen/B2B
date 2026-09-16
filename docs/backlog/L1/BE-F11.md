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

## Measured after the configuration commit (`040fac9`, 2026-09-16)

Three causes, not two — the third surfaced while re-measuring:

| Step | Findings |
|---|---|
| As is | 1184 |
| + `databaseMigrationsPath` (root + 18 module dirs) | 890 |
| + Pest's neon files + the `@param-closure-this Tests\TestCase` stub | 435 |
| + `Pest\Mixins\Expectation` as an object crate | 422 |
| + `parseModelCastsMethod: true` — Larastan was reading `casts()`'s declared return type, not its body, so every enum/datetime cast column was a `string` | **372** |

No code line touched; 0 general errors. Below 200 was the expectation; 372 is the fact.

**What the 372 are:**

| Bucket | Count | What |
|---|---|---|
| Typing debt, no runtime effect | 322 | relation methods without generic return types — every related model is a bare `Illuminate\Database\Eloquent\Model`, so `$product->media->x` and `->first()->y` are "undefined" (the ignored `missingType.generics` rule hides **213** of exactly these declarations); `array` params/returns with no value type (86); unresolved template types (52); `Builder::allowedFilters` from spatie/query-builder macros; `LengthAwarePaginator::setCollection()` on the contract type |
| Pest residue the config cannot express | 31 | `test()->postJson()` in helper functions (a stub `@return` cannot override Pest's native union return type); `arch()->expect()`; properties set on the test case in `beforeEach` (`$this->otp`, `$this->bearer`) |
| To examine | 19 | all trace to the same bare-Model typing (a `Collection<Model>::map(fn (Product $p))`, a `->first()` passed where `PlatformUser` is expected); one wrong docblock (`CartAssembler::reprice()` returns ints, declared strings) |
| Runtime defects found | **0** | — |

**Is the gate passable today?** No. 372 remain, and they are docblocks and generics —
roughly 340 sites across six modules — not configuration. Two honest routes: baseline
now and shrink under a pinned count, or a docblock pass first. Either way the number to
start from is 372, not 1184.

## Decision — (A), baseline and shrink under a pinned count

Taken 2026-09-16. (B) meant a red gate for weeks, and a red gate is exactly what
produced this ticket: eleven commits that said "phpstan passed" while nobody ran it.
(C) gives up static analysis in a financial system.

**Before generating, the `missingType.generics` ignore was lifted** — on instruction, and
against the first version of this section, which had kept it. The reason it had to go:
the 213 relation declarations that rule names are the *cause* of 173 "undefined property
on `Model`" symptoms; a baseline that froze the symptoms while hiding the cause would
have sent the payer chasing `$product->media->x` site by site instead of fixing one
`HasMany<Related, $this>` declaration. With the rule on, cause and symptoms are counted
together and paying a declaration down takes its symptoms with it. One count, one rule,
and the number only falls. Measured: 372 with the ignore, **585** without it.

Delivered on `work/be-f11`:

- `040fac9` — the configuration alone, 1184 → 372 with the ignore in place.
- `phpstan-baseline.neon` generated at **585 findings, 490 entries**, no identifier
  ignored, with the rules in its header: the sum only falls; paid down one module per
  ticket; a new finding in new code is fixed, never baselined; nothing in it is a runtime
  defect.
- `tests/Architecture/PhpstanBaselineTest.php` pins the sum **exactly** (`585`) — a rise
  fails the build, a fall asks for the pin to be lowered in the same commit. A `<=`
  ceiling was rejected: it leaves silent headroom. The test also fails if the baseline's
  include is dropped from `phpstan.neon`.
- The gate passes: `./vendor/bin/phpstan analyse` exits 0.

## The pay-down, per module — highest symptoms-per-declaration first

One small ticket per row, not one project. Each: fix the module's relation declarations
(`HasMany<Related, $this>`, `BelongsTo<Related, $this>`, …), watch its symptoms fall
with them, regenerate the baseline, lower the pin, commit together.

| Module | Baseline findings | Root declarations (`missingType.generics`) | Symptoms that fall with them (bare `Model`) | Symptoms per declaration |
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

`core`'s 110 are contract and support signatures (`Collection`, `Builder`, `Paginator`
without a type argument) that cause no symptom of their own — last, not first. `tests` is
`argument.templateType` on `expect(...)->and(...)` chains and the 31 Pest residues the
configuration cannot express.
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

- [x] `./vendor/bin/phpstan analyse` exits 0 on `main`, in CI, on the commit that closes
      this ticket — or the gate is no longer listed anywhere as one that must pass.
      (Exits 0 on `work/be-f11` with the baseline; on `main` once merged.)
- [x] The configuration commit alone takes the count from 1184 to under 450 with no
      code change, and the commit message records both numbers. (`040fac9`: 1184 → 372.)
- [x] If (A): a pinned count that a later commit can only lower. (`PhpstanBaselineTest`, pinned at 585, no identifier ignored.)

## Working rules

- The configuration commit touches `phpstan.neon`, a stub file, `ci.yml` and CLAUDE.md
  only.
- No `@phpstan-ignore` and no inline `@var` to make a site quiet; a finding is either
  fixed, baselined under (A), or stays.
- The count in this ticket is the truth as of the date above; whoever re-measures
  replaces it, with the date.
