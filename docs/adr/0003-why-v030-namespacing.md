# ADR 0003: Protocol-version-scoped namespace (`Models\v030`, `Handlers\v030`)

- Status: accepted
- Date: 2026-06-10 (recorded retroactively)

## Context

The A2A specification evolves with breaking changes between protocol
versions (v0.2.x → v0.3.0 → v1.0). A PHP SDK that flattens all models into
one namespace forces consumers into lockstep upgrades.

## Decision

Scope protocol-version-specific models and request handlers in versioned
sub-namespaces (`A2A\Models\v030`, `A2A\Handlers\v030`) and expose a
versioned entry point (`A2AProtocol_v030`). Version-agnostic models
(parts, files, security schemes) stay in `A2A\Models`.

## Consequences

- A future v1.0 implementation lands as new namespaces
  (`A2A\Models\v100`, `A2A\Handlers\v100`, a `A2AProtocolV100` entry
  point) without touching v0.3.0 consumers — both can be served from one
  codebase, matching v1.0's per-interface `protocolVersion` declaration
  model. See docs/UPGRADE-1.0.md.
- Some duplication between versioned model families is accepted as the
  cost of isolation.
- The `A2AProtocol_v030` class name itself predates PSR-12 PascalCase
  conventions; it is kept for API stability (documented exemption in
  phpcs.xml.dist) and the PascalCase rename is reserved for the v1.0
  entry point.
