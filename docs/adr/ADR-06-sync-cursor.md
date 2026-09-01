# ADR-06 — Differential sync by cursor

**Status:** Accepted (implementation in SP-14)  
**Sprint:** SP-00 records the decision  
**Source:** DOC-10 ADR-06, REQ-CM-016

## Decision

Mobile/warehouse sync asks for what changed since a `sync_cursor`, never
the full catalogue by raw timestamp. The success envelope's `meta` already
reserves `sync_cursor` for that contract.
