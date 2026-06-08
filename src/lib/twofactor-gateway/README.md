<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# @lib/twofactor-gateway

Internal module under `src/lib/` for TwoFactor Gateway frontend logic.

For the high-level consumer overview, see `doc/Frontend Reusable Surface.md`.
For the unified documentation site, run `npm run docs:build` or `npm run docs:serve`.
For focused component-level frontend work, `npm run styleguide` still starts the standalone preview.

It currently provides:
- Shared gateway types
- Admin gateway service layer
- View-model helpers for AdminSettings
- Gateway instance modal domain model
- Basic composables for gateway API/form state

## Stable surfaces

### `@lib/twofactor-gateway`

Convenience barrel for the stable frontend domain helpers already used by the app itself:

- shared types
- service functions
- composables
- view-model / form-model helpers

### `@lib/twofactor-gateway/components`

Stable component surface for the reusable admin UI blocks that the app already composes internally:

- `GatewayAdminSettings`
- `GatewaySection`
- `GatewayInstanceCard`
- `GatewayInstanceModal`
- `GatewayRoutingModal`
- `GatewayTestModal`

Use this entry point when another view in the app needs to reuse the existing gateway management UI without copying component wiring.

### Recommended granular entry points

When the consumer only needs part of the stable UI surface, prefer the narrower subpaths below instead of importing the whole barrel:

- `@lib/twofactor-gateway/components/adminSettings`
- `@lib/twofactor-gateway/components/gatewayInstanceCard`
- `@lib/twofactor-gateway/components/gatewayInstanceModal`
- `@lib/twofactor-gateway/components/gatewayRoutingModal`
- `@lib/twofactor-gateway/components/gatewayManagement`
- `@lib/twofactor-gateway/components/gatewaySection`
- `@lib/twofactor-gateway/components/gatewayTestModal`

This is the preferred pattern for app/runtime/test consumers because it avoids loading unrelated component surfaces and prevents circular import traps while preserving the same public API.

## Internal details

The following remain internal implementation details and are **not** part of the stable public surface:

- `src/components/providers/**`
- `src/components/providers/registry.ts`
- ad hoc relative imports under `src/components/**`

Those files may still evolve as the app refines guided setup flows. Consumers should prefer the stable barrels above instead of importing those internals directly.
