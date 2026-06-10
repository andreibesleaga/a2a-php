# Coverage and Documentation Summary

This document describes the project's test/coverage artefacts and how to
(re)generate them. Coverage HTML is **not** committed to the repository —
generate it locally when needed.

## Documentation (`docs/`)

- `index.html` – Static site wrapper for the Markdown documents.
- `README.md` – High-level overview of the SDK and architecture.
- `api-reference.md` – Detailed JSON-RPC method and data model reference.
- `protocol-compliance.md` – TCK status report and behavioural highlights.
- `tck-upgrade.md` – TCK version pin and upgrade procedure.
- `UPGRADE-1.0.md` – upgrade path to A2A protocol v1.0 (planning doc).
- `adr/` – architecture decision records.

## Current status

- PHPUnit test suite: `composer test` (unit + integration + e2e; no
  coverage instrumentation enabled by default).
- A2A TCK (pinned, see `docs/tck-upgrade.md`):
  `python3 ../a2a-tck/run_tck.py --sut-url http://localhost:8081 --category all`.
- Static analysis: `composer analyse` (PHPStan level 8 + baseline).
- Coding standard: `vendor/bin/phpcs -n` (PSR-12, `phpcs.xml.dist`).

## Generating coverage locally

```bash
# Install an optional coverage driver (for example, Xdebug)
pecl install xdebug

php -d zend_extension=xdebug.so -d xdebug.mode=coverage \
    ./vendor/bin/phpunit --coverage-html coverage --coverage-text
```

The resulting HTML lands in `coverage/` (git-ignored).

## Regenerating documentation

All Markdown documents are stored in source control. To rebuild the static
site or convert Markdown to HTML, use your preferred tool (for example,
`mkdocs`, `pandoc`, or GitHub Pages). Update the Markdown first, then
regenerate HTML assets if required.

## Reporting checklist

1. Verify that `composer test`, `composer analyse` and
   `vendor/bin/phpcs -n` complete without failures.
2. Confirm that the pinned A2A TCK passes across all categories
   (CI does this automatically on every PR).
3. Keep Markdown guides (`README.md`, `docs/*`, `CHANGELOG.md`)
   synchronised with behavioural changes.
