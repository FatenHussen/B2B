# Channel + warehouse v1.1 — catalog-first backlog

Features from DOC §4.2–5 / BR that need **new or extended** channel/warehouse EPs.
Per repo rules: **no EP → no build**. Add the catalog entry in its own commit,
implement behind it, then regenerate status.

## Landed in launch-gaps (2026-09-24)

| Capability | Status |
|---|---|
| OTP bypass closed on staging | `OtpService::bypassed()` = local\|testing only |
| Pricing stop-at-first-match | Engine + catalog notes |
| Assign `auto` / `bulk_zone` | Real on-duty zone match |
| DailySnapshot schedule | `reports:daily-snapshots` @ 00:05 Asia/Damascus |
| `variants[].stock` on GET product | Default warehouse ledger |
| Category tree `media_id` + `activity_type_ids` | EP-SC-011 response |
| Pick path by aisle/shelf | WH picking list order |
| Banner CTR | impressions on home-blocks + `POST /app/content/banners/{id}/click` |
| Returns `sla_due_at` / overdue | EP-SC-070 + channel `settings.returns_sla_hours` |
| Stock lots on receive + FEFO earliest expiry | Receiving body + ledger `receiveLot` / `earliestExpiry` |
| Rep live location | EP-SC-075A |
| Warehouse overdue_picks / inbound_returns alerts | EP-WH-010 queues |
| List/adjust stock lots | EP-WH-040 / 040A |
| Offline pick/pack + conflict sync | EP-WH-041A–C |
| Picking waves (multi-order pick sheet) | EP-WH-042 / 042A |
| Live map of on-duty reps | EP-SC-160 |
| Weekly delivery calendar | EP-SC-161 |
| Returns SLA escalate | EP-SC-162 |
| Zone map polygons + delivery_windows | EP-SC-163 |
| Richer retailer 360 | EP-SC-164 fields on `GET /channel/retailers/{id}` |
| Credit `manual_approval` queue | EP-SC-165 / 165A |
| Warehouse productivity / accuracy reports | EP-WH-050 |

## Still deferred (no full EP / client surface)

_(none — v1.1 channel/warehouse backlog complete)_

When picking one row: add `ep(...)` to `docs/api/catalog/05-channel-ops.php` or
`06-warehouse.php`, land the route + tests, then
`php docs/api/generate.php && php docs/status/generate.php`.
