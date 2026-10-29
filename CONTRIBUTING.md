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
git clone --branch 0.3.0.beta5 https://github.com/a2aproject/a2a-tck.git ../a2a-tck
cd ../a2a-tck && pip install -e .
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

Use clear, imperative commit subjects. Reference issues or pull requests when
applicable.

## CI and releases

Workflows live in `.github/workflows/`:

| Workflow | Runs on | What it does |
| -------- | ------- | ------------ |
| `ci.yml` (CI) | push and pull request to `main` | Job `test` on PHP 8.2, 8.3 and 8.4: `composer validate --strict`, `composer install`, PHP syntax lint, `vendor/bin/phpcs -n`, PHPStan level 8 with the baseline, `composer audit`, PHPUnit. Job `tck` (PHP 8.3): runs the pinned official TCK against the reference server. |
| `release.yml` (Release) | push of a tag `v*` | Builds a CycloneDX SBOM (`sbom.json`, no dev packages) and attaches it to the GitHub release, with generated release notes. |
| `publish-image.yml` (Publish container image) | push of a tag `v*`; manual dispatch; push to `main` that touches `Dockerfile`, `.dockerignore`, `composer.json`, `composer.lock`, `src/`, `examples/` or the workflow itself | Builds the reference-server image from `Dockerfile`, smoke-tests the agent card and `ping`, then pushes `ghcr.io/andreibesleaga/a2a-php`. Image tags: the version from a `v*` tag, the raw tag name, `latest` for the default branch, and the short commit SHA. |

Dependabot (`.github/dependabot.yml`) opens weekly update pull requests for
Composer and GitHub Actions dependencies.

Release tags in this repository look like `v0.9.6-beta`. Pushing a `v*` tag
starts `release.yml` and `publish-image.yml`. The Packagist page lists the same
version names as the tags.
