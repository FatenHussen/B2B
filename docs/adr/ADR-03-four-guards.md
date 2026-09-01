# ADR-03 — Four guards, four user tables

**Status:** Accepted (implementation deferred to SP-01 / TB-BE-101)  
**Sprint:** SP-00 records the decision; SP-01 splits the tables  
**Source:** DOC-10 ADR-03, NFR-AD-04, DOC-11A TB-BE-101

## Decision

Platform, channel, warehouse, retailer, and rep identities are separate
tables and separate Sanctum guards. A request that hits the wrong app is
rejected before any data is touched.

## Current state (SP-00)

A single `users` table with `type` remains so existing OTP/session tests
keep working. The model already lives in Identity. Splitting tables is
SP-01, before Catalog.

## Why not split in SP-00

SP-00 moves the skeleton and Core conventions. Splitting identity in the
same change would mix a structural migration with an auth rewrite.
