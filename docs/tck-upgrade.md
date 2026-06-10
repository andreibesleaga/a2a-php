# A2A TCK version pinning and upgrade procedure

## Why pin

The official [a2a-tck](https://github.com/a2aproject/a2a-tck) evolves with
the specification: tests are added (e.g. push-notification *delivery*
tests arrived in TCK PR #178, after this SDK's original 66/66 run) and its
CLI changes between protocol generations (`--sut-url` in the 0.3.x kits,
`--sut-host` in 1.x kits). An unpinned TCK makes "passes the spec" drift
silently, in either direction.

This repo pins the TCK in `.github/workflows/ci.yml`:

```yaml
env:
  A2A_TCK_REF: 0.3.0.beta5   # latest v0.3.0-era TCK tag
```

## Current verified status

Against `0.3.0.beta5` the reference server passes **every category**:

| Category | Result |
|---|---|
| Mandatory | 32 passed, 0 failed (1 skipped: N/A) |
| Capability | 39 passed, 0 failed (1 xfail, 38 skipped: undeclared capabilities) |
| Transport equivalence | skipped (single JSON-RPC transport) |
| Quality | 14 passed, 0 failed |
| Features | 15 passed, 0 failed (1 skipped) |

> Known TCK quirk at `0.3.0.beta5`: the aggregated
> `--compliance-report` JSON sometimes ingests zero totals and prints
> "Not A2A Compliant / 0.0%" even when every category passed. The
> authoritative results are the per-category console summary and the
> per-category pytest JSONs in `reports/*_results.json`.

## How to bump the pin

1. Open a dedicated PR that only changes `A2A_TCK_REF` (and, if the new
   kit changed its CLI, the TCK step commands).
2. Read the TCK diff between the old and new refs
   (`git log old..new -- tests/`) and note added/changed tests.
3. Run locally first:
   ```bash
   git clone --branch <new-ref> https://github.com/a2aproject/a2a-tck.git
   cd a2a-tck && pip install -e .
   php -S localhost:8081 examples/complete_a2a_server.php &
   python run_tck.py --sut-url http://localhost:8081 --category all
   ```
4. If new tests fail, fix the implementation in the same PR (preferred)
   or document the gap explicitly in `docs/protocol-compliance.md` —
   never bump the pin with silent failures.
5. Update the results table above and `docs/protocol-compliance.md`.

Do **not** bump to a `1.x` TCK ref: protocol v1.0 is a breaking
specification change implemented behind new namespaces — see
`docs/UPGRADE-1.0.md`.
