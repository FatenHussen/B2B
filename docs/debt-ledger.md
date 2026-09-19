# Debt ledger

Known deviations from the rules in `CLAUDE.md` that are kept on purpose. Each line says what the
debt is, why it stays, what pins it so it cannot grow, and what would retire it. A debt with no
counter is a grave: it gets added to and nobody notices. A line leaves this file in the commit that
pays it.

| Since | Debt | Why it stays | Pinned by | Retired by |
|---|---|---|---|---|
| 2026-09-17 | 16 migrations across 6 stamps share a timestamp (`2026_08_08_125139`, `2026_09_01_100000`, `2026_09_01_120000`, `2026_09_01_130000`, `2026_09_15_120000`, `2026_09_16_130000`). The rule — one unique stamp per file — applies to new files only. | Renaming a migration that has already run makes every deployed database see it as new and try to run it again. | `tests/Architecture/MigrationTimestampTest.php` lists the sixteen by name and fails on any other shared stamp. | Nothing planned. A pair leaves the pin only if a data migration proves the rename safe on every environment. |
| 2026-09-19 | The rep session (`GET /app/session`, `AppSession`) does not return the profile status or the channel. A disabled or still-pending rep receives 403 `insufficient_permission` from `EnsureRepProfileActive` on every `/app/*` route and cannot tell why from the session it can still read. | The session shape is a published contract consumed by the Flutter rep app; adding fields is a contract change to say out loud, not to slip into an ownership fix. | Nothing yet. `tests/Feature/Identity/RepProfileStatusTest.php` pins the 403 and its message; nothing pins that the session stays silent. | Add `status` and `channel` to the rep session payload, regenerate OpenAPI, and say so in the pull request. |
| 2026-09-19 | OTP is off on `api.sentraxsy.com`: `APP_ENV=staging` with `OTP_BYPASS=true` on a production domain (`docs/deploy/current-state.md`). `OtpService` disables the bypass only under `isProduction()`, so anyone who knows a registered phone number logs in as that account with any code. **High severity.** | Deliberate for the build-out — the Flutter clients hard-code the code and no real user exists yet. | `tests/Feature/Identity/OtpBypassTest.php` proves the bypass is dead under `APP_ENV=production`; nothing pins the server's env. | `APP_ENV=production` and `OTP_BYPASS=false` on the server before the first real user. |
