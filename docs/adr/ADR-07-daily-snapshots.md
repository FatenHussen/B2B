# ADR-07 — Daily snapshots for dashboards

**Status:** Accepted (implementation in SP-16)  
**Sprint:** SP-00 records the decision  
**Source:** DOC-10 ADR-07, BR-AD-23, REQ-AD-010

## Decision

Operational dashboards read pre-aggregated daily rows per channel, not live
transaction tables. The `snapshots:generate` job (DOC-11A §4.3) owns that
path. Reporting must not query live orders for KPI tiles.
