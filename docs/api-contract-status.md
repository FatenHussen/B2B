# API contract status

Layer 1 tickets carry endpoint identifiers taken from `docs/api/catalog/*.php`, as named in the five
product briefs. They are implementable.

Layers 2, 3 and 4 do **not** carry endpoint identifiers, because the catalog was never published for them.
Every ticket in those layers has `contract: proposed` in its front matter.

## The rule

An agent asked to implement a ticket with `contract: proposed` must stop and say the contract is not fixed.
It must not invent a path, a request body or a response shape. The Layer 1 discipline was that no screen is
ever built against an invented contract; that discipline does not lapse in Layer 2.

## To unblock a layer

1. Publish the endpoints in `docs/api/catalog/`, each with `b` (request body), `r` (data shape) and `e` (error codes).
2. Add the `EP-ID` values to the `endpoints:` front matter of each ticket in both repositories.
3. Change `contract: proposed` to `contract: confirmed`.
4. Only then pull the ticket into a sprint.
