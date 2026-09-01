# ADR-02 — app-modules + Composer path + Deptrac

**Status:** Accepted  
**Sprint:** SP-00  
**Source:** DOC-10 §1.3

## Decision

Business logic lives in `app-modules/*` as Composer path packages
(`b2b/{name}`). Deptrac enforces the four layers in CI. We do **not** use
`nwidart/laravel-modules`.

## Why

nwidart adds a magic loader, enable/disable lifecycle, and a third-party
release cycle in the spine of the project. Path packages give real Composer
boundaries: a module cannot import what it does not require.

## Consequences

- Root `composer.json` lists every `b2b/*` package.
- Laravel discovers providers via `extra.laravel.providers`.
- The previous `Modules/` tree (nwidart) is removed after this sprint.
