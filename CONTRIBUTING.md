# Contributing to a2a-php

Thanks for your interest in improving the A2A Protocol PHP SDK.

## Ground rules

1. **Backward compatibility is a hard constraint.** The public API
   (class names, method signatures, JSON-RPC behavior, env var defaults)
   of the `v0.3.0` implementation must not change. Breaking work targets
   the future protocol v1.0 namespace — see `docs/UPGRADE-1.0.md`.
2. **The official A2A TCK must stay green.** CI runs the
   [a2a-tck](https://github.com/a2aproject/a2a-tck) pinned at the version
   recorded in `.github/workflows/ci.yml` (`A2A_TCK_REF`). Any PR that
   breaks a TCK category is rejected. TCK version bumps are deliberate,
   separate PRs — see `docs/tck-upgrade.md`.

## Development setup

```bash
composer install
composer test       # PHPUnit (unit + integration + e2e suites)
composer analyse    # PHPStan level 8 (baseline-ratcheted)
composer style      # PHPCS, PSR-12
```

Run the reference server locally:

```bash
php -S localhost:8081 examples/complete_a2a_server.php
```

Run the official TCK against it:

```bash
git clone --branch 0.3.0.beta5 https://github.com/a2aproject/a2a-tck.git
cd a2a-tck && pip install -e .
python run_tck.py --sut-url http://localhost:8081 --category all
```

## Pull request checklist

- [ ] `composer test` passes (new behavior covered by golden-path,
      edge-case and error-path tests).
- [ ] `vendor/bin/phpcs -n` reports no errors.
- [ ] `vendor/bin/phpstan analyse` passes; do not grow
      `phpstan-baseline.neon` — fix new findings instead.
- [ ] No public API change (or the PR is explicitly labeled `breaking`
      and targets the next major version).
- [ ] Docs updated when behavior or commands change (`README.md`,
      `docs/`).

## Commit style

Use clear, imperative commit subjects. Reference audit recommendation IDs
(R1–R12) or issues when applicable.
