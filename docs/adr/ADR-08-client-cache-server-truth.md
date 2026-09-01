# ADR-08 — Client cache, server is source of truth

**Status:** Accepted (clients implement in their repos)  
**Sprint:** SP-00 records the decision  
**Source:** DOC-10 ADR-08, REQ-CM-017

## Decision

SQLite/Drift on mobile and IndexedDB/Dexie in the warehouse are display
caches. The API is always the source of truth. Offline submits are accepted
and re-priced on the server (REQ-CM-018) with a difference notice.
