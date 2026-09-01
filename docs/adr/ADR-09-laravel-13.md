# ADR-09 — Laravel 13, not Laravel 11

**Status:** Accepted  
**Sprint:** SP-00  
**Source:** DOC-11A names Laravel 11; this project already runs 13.8

## Decision

Stay on Laravel `^13.8` and PHP `^8.3`. DOC-11A's Laravel 11 pin is a
draft-time snapshot, not an architectural constraint.

## Why this is allowed

ADR-01 through ADR-08 do not depend on the 11 vs 13 major. Composer path
packages, Sanctum, Horizon, and Deptrac all work on 13. Downgrading would
discard security and framework fixes for no module-boundary gain.

## What we still follow from DOC-11A

Package *roles* (Sanctum, Horizon, Permission, Query Builder, Media Library,
Data, Activity Log, Excel, DomPDF, Pusher, Sentry, Phone) and Sprint 0
gates. Versions track Laravel 13 compatibility, not the draft `^11.0` pins.
