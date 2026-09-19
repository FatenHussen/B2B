Implement one plan ticket, end to end, without touching anything outside it.

Usage: /implement-ticket PA-03   (platform admin, docs/plan/platform-admin.md)
       /implement-ticket AP-02   (apps and shared surface, docs/plan/apps.md)

1. Read `CLAUDE.md` in full. This repository is an API: no Blade, no views, no redirects.
2. Find `$ARGUMENTS` in `docs/plan/platform-admin.md` or `docs/plan/apps.md` and read its section: module,
   tables, permissions, dependencies, acceptance lines.
3. For every `EP-` the section names, read its `ep()` entry in `docs/api/catalog/*.php`. That is the
   request and response contract. If an endpoint is not in the catalog, STOP and say so. Do not invent a
   path, a request body or a response shape.
4. Check `docs/status/` for each route: if it is already ✅, STOP and say which.
5. Check the ticket's "يعتمد على" column: if a dependency is still ⬜, STOP and say which.
6. Restrict every edit to the module(s) the ticket names, plus its tests and the catalog if the ticket says so.
7. Write one test per acceptance line, named after it.
8. Run and fix until all pass:
   - `composer deptrac`
   - `./vendor/bin/pest --group=arch`
   - `./vendor/bin/phpstan analyse`
   - `./vendor/bin/pest`
   - `./vendor/bin/pint --test`
9. `php docs/api/generate.php && php docs/status/generate.php`; confirm the routes are ✅; flip the
   ticket's status in the plan table to ✅ with today's date.
10. Report: files changed, tests added, each acceptance line with the test that proves it, and whether
    a response shape changed.

Do not implement a second ticket. Do not refactor outside the named directories. If the ticket cannot be
completed as written, say why rather than working around it.
