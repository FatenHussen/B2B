# ADR-01 — Modular Monolith

**Status:** Accepted  
**Sprint:** SP-00  
**Source:** DOC-10 §1

## Decision

The platform is a modular monolith: one deploy, one database, immediate
transactions, with hard module boundaries. It is not a set of microservices.

## Why

- Financial consistency is immediate (rep wallet = collections − handed over).
- Multi-channel order split is one transaction, not a distributed saga.
- Team size cannot operate a service mesh.
- A clean module can be extracted later; tangled code cannot.

## Consequences

All 22 modules live in `app-modules/` and ship in one Laravel application.
Cross-module calls go through contracts or events only.
