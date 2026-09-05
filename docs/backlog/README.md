# Backend backlog

One markdown file per ticket. **179 backend tickets** across four layers.
The client tickets live in the separate frontend repository.

| Layer | Tickets | Scope | Status |
|---|---|---|---|
| [L1](./L1/README.md) | 97 | Foundation: Core, Identity, Access, Reference, Tenancy, Integration | Implementable — real EP-IDs |
| [L2](./L2/README.md) | 26 | Catalog, Pricing, Promotion, Ordering | Blocked — contracts proposed |
| [L3](./L3/README.md) | 32 | Inventory, Fulfillment, Delivery, Returns, Finance, Sync | Blocked — contracts proposed |
| [L4](./L4/README.md) | 24 | Content, Notification, Loyalty, Reporting, Support, Billing, hardening | Blocked — contracts proposed |

## Working a ticket

```
/implement-ticket BE-F01
```

## Do these five before anything else

1. `BE-F01` repository, Docker and the four environments
2. `BE-F02` module scaffold with Composer path repositories
3. `BE-F03` Deptrac dependency rules in CI
4. `BE-F04` Pest architecture tests
5. `BE-F05` base migrations and naming conventions

They are the machine that reviews every later ticket. Nothing else should start first.
