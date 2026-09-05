Implement one backlog ticket, end to end, without touching anything outside it.

Usage: /implement-ticket BE-C03

1. Read `CLAUDE.md` in full. This repository is an API: no Blade, no views, no redirects.
2. Read `docs/backlog/**/$ARGUMENTS.md` in full, including the front matter.
3. If `contract: proposed` appears, STOP and report that the endpoint contract is not fixed in the API
   catalog. Do not invent a path, a request body or a response shape.
4. Read the `Working rules` section and restrict every edit to the directories it names.
5. Read every ticket under `blocked_by` and confirm each is implemented. If one is not, STOP and say which.
6. Implement the requirements in order.
7. Write one test per acceptance criterion, named after the criterion.
8. Run and fix until all pass:
   - `composer deptrac`
   - `./vendor/bin/pest --group=arch`
   - `./vendor/bin/phpstan analyse`
   - `./vendor/bin/pest`
   - `./vendor/bin/pint --test`
9. Report: files changed, tests added, each acceptance criterion with the test that proves it, and whether
   the OpenAPI document changed.

Do not implement a second ticket. Do not refactor outside the named directories. If the ticket cannot be
completed as written, say why rather than working around it.
