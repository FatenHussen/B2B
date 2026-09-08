# Layer 1 — foundation

**Sprints:** SP-00 to SP-04

Every ticket carries a real endpoint identifier. **This layer is implementable now.**

**97 tickets · 378 points**

| Ticket | Module | Sprint | Pts | Priority | Title |
|---|---|---|---|---|---|
| [BE-C01](./BE-C01.md) | Core | SP-00 | 3 | Highest | Unified response envelope |
| [BE-C02](./BE-C02.md) | Core | SP-00 | 5 | Highest | Error handler and HTTP status map |
| [BE-C03](./BE-C03.md) | Core | SP-00 | 5 | Highest | Idempotency middleware |
| [BE-C04](./BE-C04.md) | Core | SP-00 | 5 | Highest | Immutable audit log |
| [BE-C05](./BE-C05.md) | Core | SP-00 | 5 | Highest | Generic dual-approval mechanism |
| [BE-C06](./BE-C06.md) | Core | SP-00 | 3 | Highest | Money value object and integer columns |
| [BE-C07](./BE-C07.md) | Core | SP-00 | 5 | High | State machine base and event tables |
| [BE-C08](./BE-C08.md) | Core | SP-00 | 3 | Highest | Rate limiting |
| [BE-C09](./BE-C09.md) | Core | SP-00 | 3 | High | List contract: pagination, filtering, sorting, search |
| [BE-C10](./BE-C10.md) | Core | SP-00 | 3 | High | Background job contract |
| [BE-C11](./BE-C11.md) | Core | SP-03 | 2 | Medium | Remove the manual channel filters made redundant by BelongsToChannel |
| [BE-F01](./BE-F01.md) | — | SP-00 | 5 | Highest | Repository and the three environments |
| [BE-F02](./BE-F02.md) | — | SP-00 | 5 | Highest | Module scaffold with Composer path repositories |
| [BE-F03](./BE-F03.md) | — | SP-00 | 3 | Highest | Deptrac dependency rules in CI |
| [BE-F04](./BE-F04.md) | — | SP-00 | 3 | Highest | Pest architecture tests |
| [BE-F05](./BE-F05.md) | — | SP-00 | 3 | High | Base migrations and naming conventions |
| [BE-F06](./BE-F06.md) | — | SP-00 | 5 | High | OpenAPI contract and generated clients |
| [BE-F07](./BE-F07.md) | — | SP-00 | 3 | High | Queues and Horizon |
| [BE-F08](./BE-F08.md) | — | SP-00 | 3 | Medium | Observability: Sentry, Pulse and alerting |
| [BE-F09](./BE-F09.md) | Core | SP-00 | 1 | Highest | Health endpoint |
| [BE-F10](./BE-F10.md) | — | SP-00 | 3 | Medium | Zero-downtime deployment pipeline |
| [BE-Q01](./BE-Q01.md) | — | SP-00 | 3 | Highest | Architecture test enforcement gate |
| [BE-X04](./BE-X04.md) | Integration | SP-00 | 2 | Medium | Error tracking adapter |
| [BE-I01](./BE-I01.md) | Identity | SP-01 | 5 | Highest | Four guards and separate user tables |
| [BE-I02](./BE-I02.md) | Identity | SP-01 | 5 | Highest | OTP storage and security policy |
| [BE-I03](./BE-I03.md) | Identity | SP-01 | 3 | Highest | Request OTP endpoint |
| [BE-I04](./BE-I04.md) | Identity | SP-01 | 5 | Highest | Verify OTP and device binding |
| [BE-I05](./BE-I05.md) | Identity | SP-01 | 2 | High | Resend OTP with channel preference |
| [BE-I06](./BE-I06.md) | Identity | SP-01 | 5 | Highest | Retailer registration |
| [BE-I08](./BE-I08.md) | Identity | SP-01 | 3 | Highest | App session endpoint |
| [BE-I09](./BE-I09.md) | Identity | SP-01 | 2 | High | App logout and token revocation |
| [BE-I10](./BE-I10.md) | Identity | SP-01 | 5 | Highest | Channel OTP sign-in |
| [BE-I11](./BE-I11.md) | Identity | SP-01 | 5 | Highest | Warehouse device provisioning and login |
| [BE-I12](./BE-I12.md) | Identity | SP-01 | 5 | Highest | Platform login and TOTP |
| [BE-I13](./BE-I13.md) | Identity | SP-01 | 3 | High | Platform account profile and password |
| [BE-I14](./BE-I14.md) | Identity | SP-01 | 3 | Medium | Session listing and revocation |
| [BE-I15](./BE-I15.md) | Identity | SP-01 | 5 | High | Two-factor enrolment and recovery codes |
| [BE-I16](./BE-I16.md) | Identity | SP-01 | 3 | Medium | Personal API tokens |
| [BE-I17](./BE-I17.md) | Identity | SP-01 | 3 | Highest | Password confirmation window |
| [BE-I18](./BE-I18.md) | Identity | SP-01 | 3 | Medium | Guest mode and write blocking |
| [BE-Q03](./BE-Q03.md) | — | SP-01 | 3 | High | Contract snapshot tests for mobile resources |
| [BE-Q05](./BE-Q05.md) | — | SP-01 | 3 | Medium | Load test on the identity path |
| [BE-X01](./BE-X01.md) | Integration | SP-01 | 5 | Highest | WhatsApp Business API adapter |
| [BE-X02](./BE-X02.md) | Integration | SP-01 | 3 | Highest | SMS fallback adapter |
| [BE-X03](./BE-X03.md) | Integration | SP-01 | 3 | Medium | Media storage adapter |
| [BE-X05](./BE-X05.md) | Integration | SP-01 | 3 | Medium | Integration health and settings |
| [BE-A01](./BE-A01.md) | Access | SP-02 | 5 | Highest | Permission catalog seed |
| [BE-A02](./BE-A02.md) | Access | SP-02 | 3 | High | Permission catalog and holders API |
| [BE-A03](./BE-A03.md) | Access | SP-02 | 5 | Highest | Roles with draft lifecycle |
| [BE-A04](./BE-A04.md) | Access | SP-02 | 5 | High | Role preview with SoD conflict detection |
| [BE-A05](./BE-A05.md) | Access | SP-02 | 3 | Highest | Role approval by a second user |
| [BE-A06](./BE-A06.md) | Access | SP-02 | 3 | Medium | Replace role permissions |
| [BE-A07](./BE-A07.md) | Access | SP-02 | 5 | High | Bulk role assignment |
| [BE-A08](./BE-A08.md) | Access | SP-02 | 2 | Medium | Revoke assignment |
| [BE-A09](./BE-A09.md) | Access | SP-02 | 5 | Medium | Permission simulator |
| [BE-A10](./BE-A10.md) | Access | SP-02 | 5 | Medium | Temporary grants |
| [BE-A11](./BE-A11.md) | Access | SP-02 | 3 | High | Segregation of duties rules |
| [BE-A12](./BE-A12.md) | Access | SP-02 | 5 | Highest | Approval inbox and decisions |
| [BE-A13](./BE-A13.md) | Access | SP-02 | 5 | High | Audit query API |
| [BE-A14](./BE-A14.md) | Access | SP-02 | 3 | Medium | Audit export job |
| [BE-A15](./BE-A15.md) | Access | SP-02 | 5 | Medium | Quarterly access review campaigns |
| [BE-A16](./BE-A16.md) | Access | SP-02 | 5 | Highest | Two-level authorisation enforcement |
| [BE-Q04](./BE-Q04.md) | — | SP-02 | 5 | Highest | Authorisation bypass test suite |
| [BE-Q06](./BE-Q06.md) | — | SP-02 | 5 | High | Security hardening pass |
| [BE-R01](./BE-R01.md) | Reference | SP-03 | 5 | Highest | Reference module core and soft-delete policy |
| [BE-R02](./BE-R02.md) | Reference | SP-03 | 3 | Highest | Governorates |
| [BE-R03](./BE-R03.md) | Reference | SP-03 | 5 | Highest | Zones with impact counts |
| [BE-R04](./BE-R04.md) | Reference | SP-03 | 2 | High | Activity types |
| [BE-R05](./BE-R05.md) | Reference | SP-03 | 3 | High | Root categories |
| [BE-R06](./BE-R06.md) | Reference | SP-03 | 3 | Medium | Sale units |
| [BE-R07](./BE-R07.md) | Reference | SP-03 | 2 | Low | Equipment |
| [BE-R08](./BE-R08.md) | Reference | SP-03 | 3 | High | Currencies and decimals |
| [BE-R09](./BE-R09.md) | Reference | SP-03 | 5 | High | FX rates |
| [BE-R10](./BE-R10.md) | Reference | SP-03 | 5 | Highest | Public references endpoint |
| [BE-R11](./BE-R11.md) | Reference | SP-03 | 5 | Medium | Reference import with dry run |
| [BE-R12](./BE-R12.md) | Reference | SP-03 | 2 | High | Mandatory reason on reference mutations |
| [BE-I07](./BE-I07.md) | Identity | SP-04 | 5 | High | Rep registration with channel invite |
| [BE-Q02](./BE-Q02.md) | — | SP-04 | 3 | Highest | Tenant isolation coverage gate |
| [BE-T01](./BE-T01.md) | Tenancy | SP-04 | 5 | Highest | Supply channels and the channel state machine |
| [BE-T02](./BE-T02.md) | Tenancy | SP-04 | 5 | Highest | Tenant isolation: trait, scope and 404 policy |
| [BE-T03](./BE-T03.md) | Tenancy | SP-04 | 5 | Highest | Tenant isolation test suite |
| [BE-T04](./BE-T04.md) | Tenancy | SP-04 | 8 | Highest | Create channel |
| [BE-T05](./BE-T05.md) | Tenancy | SP-04 | 5 | Highest | Provisioning job and idempotent retry |
| [BE-T06](./BE-T06.md) | Tenancy | SP-04 | 3 | High | Channel read and update |
| [BE-T07](./BE-T07.md) | Tenancy | SP-04 | 5 | High | Channel users and manager invite reset |
| [BE-T08](./BE-T08.md) | Tenancy | SP-04 | 5 | High | Coverage and overlap detection |
| [BE-T09](./BE-T09.md) | Tenancy | SP-04 | 2 | Low | Channel warehouses (read) |
| [BE-T10](./BE-T10.md) | Tenancy | SP-04 | 3 | Medium | Feature flags: plan versus override |
| [BE-T11](./BE-T11.md) | Tenancy | SP-04 | 3 | Medium | Channel usage series |
| [BE-T12](./BE-T12.md) | Tenancy | SP-04 | 5 | High | Limits, overrides and plan enforcement |
| [BE-T13](./BE-T13.md) | Tenancy | SP-04 | 3 | High | Channel status transitions |
| [BE-T14](./BE-T14.md) | Tenancy | SP-04 | 3 | Medium | Channel data export |
| [BE-T15](./BE-T15.md) | Tenancy | SP-04 | 5 | High | Channel deletion request |
| [BE-T16](./BE-T16.md) | Tenancy | SP-04 | 5 | Highest | Channel list and export |
| [BE-T17](./BE-T17.md) | Tenancy | SP-04 | 3 | Medium | Bulk manager notification |
| [BE-T18](./BE-T18.md) | Tenancy | SP-04 | 5 | Medium | Plan change preview and apply |
| [BE-T19](./BE-T19.md) | Tenancy | SP-04 | 5 | High | Channel join applications |
| [BE-T20](./BE-T20.md) | Tenancy | SP-04 | 3 | High | Rep invite link issuance |

## How to work this layer

1. Take tickets in sprint order. Within a sprint, follow `blocked_by`.
2. One ticket per session. Do not batch.
3. Run `/implement-ticket {ID}` and let it stop you if a dependency is missing.
