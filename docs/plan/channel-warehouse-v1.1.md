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
| Credit `manual_approval` | Documented ≡ block (423) |
| Banner CTR | impressions on home-blocks + `POST /app/content/banners/{id}/click` |
| Returns `sla_due_at` / overdue | EP-SC-070 + channel `settings.returns_sla_hours` |
| Stock lots on receive + FEFO earliest expiry | Receiving body + ledger `receiveLot` / `earliestExpiry` |
| Rep live location | EP-SC-075A |
| Warehouse overdue_picks / inbound_returns alerts | EP-WH-010 queues |
| Richer retailer 360 | EP-SC-164 fields on `GET /channel/retailers/{id}` (same handler as EP-SC-140A) |

## Still deferred (no full EP / client surface)

Priority is product order for remaining post-launch work.

| Priority | Reserved EP id(s) | Surface | Capability | Notes |
|---|---|---|---|---|
| 1 | EP-WH-040 | warehouse | List/adjust stock lots UI | Lots table exists; no dedicated list/adjust EP yet. |
| 2 | EP-WH-041A–B | warehouse | Offline pick/pack + conflict sync | Needs device cursor + conflict codes. |
| 3 | EP-WH-042 / 042A | warehouse | Batch picking / multi-order pick list | ✅ POST+GET picking-waves (2026-09-24) |
| 4 | EP-SC-160 | channel | Live **map** of all on-duty reps | Single-rep `/reps/{id}/live` exists; aggregate map does not. |
| 5 | EP-SC-161 | channel | Weekly channel delivery calendar | Coverage today is `delivery_days` on zones only. |
| 6 | EP-SC-162 | channel | Returns SLA escalation actions | List shows overdue; no escalate/notify EP. |
| 7 | EP-SC-163 | channel | Zone map polygons + delivery time windows | Zones are reference entities; polygons absent. |
| 8 | EP-WH-050 | warehouse | Productivity / accuracy reports | Snapshots cover channel ops; WH-specific missing. |
| 9 | EP-SC-165 | channel | Credit `manual_approval` queue | Until then `manual_approval` ≡ block (423). |

When picking one row: add `ep(...)` to `docs/api/catalog/05-channel-ops.php` or
`06-warehouse.php`, land the route + tests, then
`php docs/api/generate.php && php docs/status/generate.php`.
