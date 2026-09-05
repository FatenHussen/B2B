# Binding conventions

From DOC-10 section 5.

| Area | Convention | Source | Enforced by |
|---|---|---|---|
| Modules | app-modules/ with local Composer path repositories; boundaries enforced by Deptrac in CI | DOC-10 §1.3, §4.3 | BE-F02 / BE-F03 |
| Dependency direction | Downward only. Coordination → Domain → Foundation. Foundation depends on nothing. | DOC-10 §2.1 | BE-F03 |
| Cross-module calls | Public Contracts or events only. Never another module Eloquent model, never its tables. | DOC-10 §2.2 | BE-F04 |
| Cross-module relations | By identifier, never by an Eloquent relation across a boundary. | DOC-10 §2.2 | BE-F04 |
| Events | Notification, not control. The emitting module does not wait for a result. | DOC-10 §2.2 | BE-C07 |
| Tenancy | supply_channel_id on every channel-owned table with a composite index starting on it; ChannelScope applied automatically. | DOC-10 §5.1, DOC-02 §7.4 | BE-T02 |
| Tenancy failure mode | A foreign channel id in the URL returns 404, not 403. | DOC-10 §5.1 (AC-PM-02) | BE-T02 |
| Money | bigInteger in the smallest currency unit plus a Money value object. float and double are forbidden. | DOC-10 §5.2 (BR-AD-18) | BE-C06 |
| Money presentation | Rounding happens in MoneyResource only; storage stays integer. | DOC-10 §5.2 | BE-C06 |
| State machines | status is guarded and changed only through the lifecycle service; every transition is logged; an illegal transition raises 409. | DOC-10 §5.3 (BR-AD-13) | BE-C07 |
| Idempotency | Every write accepts X-Idempotency-Key. Completed key replays, in-flight key returns 409, new key executes and stores for 24 hours. | DOC-10 §5.4 (BR-AD-24) | BE-C03 |
| Response envelope | Success { data, meta }, error { error: { code, message, details } }. The code is the contract; the message is for the human. | DOC-10 §5.5 | BE-C01 |
| Naming — tables | Plural snake_case, for example sub_orders. | DOC-10 §5.6 | BE-F05 |
| Naming — routes | kebab-case and plural, for example /api/v1/channel/sub-orders. | DOC-10 §5.6 | BE-F05 |
| Naming — permissions | Taken from the DOC-08 catalog literally, for example sc.orders.confirm. | DOC-10 §5.6 | BE-A01 |
| Naming — events and jobs | Events are past tense (SubOrderConfirmed); jobs and actions are imperative verbs (SubmitOrder). | DOC-10 §5.6 | BE-F05 |
| Queues | critical (OTP, handover, payments, outbox) · default · media · reports, supervised by Horizon. | DOC-10 §5.7 | BE-F07 |
| Testing | Unit ≥ 90% on engines and money; feature ≥ 75% on endpoints, permissions and isolation; Larastan level 6. | DOC-02 §17 | BE-Q04 |
