# Layer 2 — commercial core

**Sprints:** SP-05 to SP-08

**Contract status: proposed.** Fix the paths in the API catalog before starting any of these.

**26 tickets · 158 points**

| Ticket | Module | Sprint | Pts | Priority | Title |
|---|---|---|---|---|---|
| [BE2-CAT01](./BE2-CAT01.md) | Catalog | SP-05 | 5 | Highest | Brands |
| [BE2-CAT02](./BE2-CAT02.md) | Catalog | SP-05 | 8 | Highest | Five-level category tree |
| [BE2-CAT03](./BE2-CAT03.md) | Catalog | SP-05 | 8 | Highest | Products |
| [BE2-CAT04](./BE2-CAT04.md) | Catalog | SP-05 | 5 | High | Variants and combination generation |
| [BE2-CAT05](./BE2-CAT05.md) | Catalog | SP-05 | 5 | High | Media library and image processing |
| [BE2-CAT06](./BE2-CAT06.md) | Catalog | SP-05 | 8 | High | Catalog import and export |
| [BE2-CAT07](./BE2-CAT07.md) | Catalog | SP-06 | 8 | Highest | Retailer and rep catalog read API |
| [BE2-CAT08](./BE2-CAT08.md) | Catalog | SP-06 | 5 | High | Text and barcode search |
| [BE2-PRC01](./BE2-PRC01.md) | Pricing | SP-06 | 8 | Highest | Price lists and rule types |
| [BE2-PRC02](./BE2-PRC02.md) | Pricing | SP-06 | 5 | High | Quantity tiers |
| [BE2-PRC03](./BE2-PRC03.md) | Pricing | SP-06 | 8 | Highest | Pricing engine with rule precedence |
| [BE2-PRC04](./BE2-PRC04.md) | Pricing | SP-06 | 5 | High | Price change log and controls |
| [BE2-ORD01](./BE2-ORD01.md) | Ordering | SP-07 | 5 | Highest | Cart model |
| [BE2-ORD02](./BE2-ORD02.md) | Ordering | SP-07 | 5 | Highest | Cart totals and discount rules |
| [BE2-ORD03](./BE2-ORD03.md) | Ordering | SP-07 | 8 | Highest | Order submission and multi-channel split |
| [BE2-PRC05](./BE2-PRC05.md) | Pricing | SP-07 | 5 | Highest | Price freeze on the order |
| [BE2-PRM01](./BE2-PRM01.md) | Promotion | SP-07 | 8 | Highest | Offer types |
| [BE2-PRM02](./BE2-PRM02.md) | Promotion | SP-07 | 5 | High | Targeting and constraints |
| [BE2-PRM03](./BE2-PRM03.md) | Promotion | SP-07 | 8 | Highest | Cart evaluation engine |
| [BE2-PRM04](./BE2-PRM04.md) | Promotion | SP-07 | 5 | Highest | Indivisibility and quota reservation |
| [BE2-PRM05](./BE2-PRM05.md) | Promotion | SP-07 | 3 | Medium | Offer performance report |
| [BE2-ORD04](./BE2-ORD04.md) | Ordering | SP-08 | 8 | Highest | Sub-order state machine |
| [BE2-ORD05](./BE2-ORD05.md) | Ordering | SP-08 | 5 | Highest | Channel order inbox |
| [BE2-ORD06](./BE2-ORD06.md) | Ordering | SP-08 | 5 | High | Bulk confirm, reject and schedule |
| [BE2-ORD07](./BE2-ORD07.md) | Ordering | SP-08 | 5 | High | Order detail and timeline |
| [BE2-ORD08](./BE2-ORD08.md) | Ordering | SP-08 | 5 | High | Retailer order list and detail API |

## How to work this layer

1. Take tickets in sprint order. Within a sprint, follow `blocked_by`.
2. One ticket per session. Do not batch.
3. Run `/implement-ticket {ID}` and let it stop you if a dependency is missing.
