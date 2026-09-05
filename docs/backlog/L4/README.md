# Layer 4 — growth and launch

**Sprints:** SP-14 to SP-18

**Contract status: proposed.** Fix the paths in the API catalog before starting any of these.

**24 tickets · 138 points**

| Ticket | Module | Sprint | Pts | Priority | Title |
|---|---|---|---|---|---|
| [BE4-CNT01](./BE4-CNT01.md) | Content | SP-14 | 3 | Medium | Intro and legal pages |
| [BE4-CNT02](./BE4-CNT02.md) | Content | SP-14 | 5 | High | Banners |
| [BE4-CNT03](./BE4-CNT03.md) | Content | SP-14 | 8 | High | Dynamic sliders and algorithms |
| [BE4-CNT04](./BE4-CNT04.md) | Content | SP-14 | 3 | Medium | App version gating |
| [BE4-NTF01](./BE4-NTF01.md) | Notification | SP-14 | 5 | High | Notification model and targeting |
| [BE4-NTF02](./BE4-NTF02.md) | Notification | SP-14 | 5 | High | Automatic event templates |
| [BE4-NTF03](./BE4-NTF03.md) | Notification | SP-14 | 5 | Medium | Channels, scheduling and delivery log |
| [BE4-LOY01](./BE4-LOY01.md) | Loyalty | SP-15 | 5 | Medium | Point rules |
| [BE4-LOY02](./BE4-LOY02.md) | Loyalty | SP-15 | 5 | Medium | Tiers and rewards |
| [BE4-RPT01](./BE4-RPT01.md) | Reporting | SP-15 | 5 | High | Daily snapshots |
| [BE4-RPT02](./BE4-RPT02.md) | Reporting | SP-15 | 8 | High | Channel dashboard figures |
| [BE4-RPT03](./BE4-RPT03.md) | Reporting | SP-15 | 8 | Medium | The nine report families |
| [BE4-HRD01](./BE4-HRD01.md) | — | SP-16 | 8 | Highest | Performance hardening |
| [BE4-HRD02](./BE4-HRD02.md) | — | SP-16 | 8 | Highest | Security hardening and penetration pass |
| [BE4-RPT04](./BE4-RPT04.md) | Reporting | SP-16 | 5 | Medium | Platform-level reporting |
| [BE4-SUP01](./BE4-SUP01.md) | Support | SP-16 | 5 | Medium | Tickets and unified search |
| [BE4-SUP02](./BE4-SUP02.md) | Support | SP-16 | 5 | High | Impersonation with guardrails |
| [BE4-SUP03](./BE4-SUP03.md) | Support | SP-16 | 3 | Medium | System health view |
| [BE4-BIL01](./BE4-BIL01.md) | PlatformBilling | SP-17 | 8 | Medium | Plans and subscriptions |
| [BE4-BIL02](./BE4-BIL02.md) | PlatformBilling | SP-17 | 5 | Medium | Platform invoices and dunning |
| [BE4-BIL03](./BE4-BIL03.md) | PlatformBilling | SP-17 | 5 | Medium | Platform team management |
| [BE4-HRD03](./BE4-HRD03.md) | — | SP-17 | 8 | Highest | Field acceptance criteria verification |
| [BE4-HRD04](./BE4-HRD04.md) | — | SP-17 | 8 | Highest | Pilot launch |
| [BE4-HRD05](./BE4-HRD05.md) | — | SP-18 | 5 | Highest | General release readiness |

## How to work this layer

1. Take tickets in sprint order. Within a sprint, follow `blocked_by`.
2. One ticket per session. Do not batch.
3. Run `/implement-ticket {ID}` and let it stop you if a dependency is missing.
