Run every guardrail and report what fails, without fixing anything.

```bash
composer deptrac
./vendor/bin/pest --group=arch
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
```

For each failure report the rule broken, the file, and which rule in `CLAUDE.md` it maps to.
Do not change code.
