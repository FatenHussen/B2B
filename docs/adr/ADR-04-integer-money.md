# ADR-04 — Money as integers

**Status:** Accepted  
**Sprint:** SP-00  
**Source:** DOC-10 §5.2, BR-AD-18

## Decision

Every monetary amount is stored as `BIGINT` in the currency's minor unit
(qirsh / cents). Domain code uses `Modules\Core\Domain\ValueObjects\Money`.
`float` / `double` are forbidden on money columns. Rounding happens only
in presentation.

## Consequences

`channel_zone.delivery_fee` and `min_order_value` store minor units.
The HTTP resource still renders a two-decimal string so clients are not
broken during this sprint.
