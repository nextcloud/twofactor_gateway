<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

This styleguide documents the **public frontend surface** of Two-Factor Gateway in the same general model used by `nextcloud-vue`:

- curated markdown sections for high-level guidance
- component pages sourced from the Vue files themselves
- stable import lines shown from public entrypoints instead of internal paths

## What belongs here

Only frontend pieces intentionally exposed through the stable public surface:

```js static
import { GatewayInstanceCard } from '@lib/twofactor-gateway/components/gatewayInstanceCard'
import { GatewayInfo, useGatewayApi } from '@lib/twofactor-gateway'
```

## Import rules

- Import reusable frontend code only from `@lib/twofactor-gateway` or `@lib/twofactor-gateway/components/*`
- Prefer granular component entrypoints over the root component barrel when the consumer only needs one component
- Treat Two Factor Gateway as the source of truth for gateway payloads, admin OCS API wrappers and guided setup workflows

## What stays internal

Do **not** import the following from another app:

- `src/components/providers/**`
- `src/components/providers/registry.ts`
- `src/views/**` directly
- ad hoc relative imports into `src/components/**`

## Reading the component sections

The component pages are split by coupling level:

- **Containers** own loading and mutation workflows
- **Reusable building blocks** accept input data or emit user intent with lower coupling

If a prop table shows a non-primitive type name such as `FieldDefinition[]`, `GatewayInfo` or `GatewayGroup[]`, open the [`Shared frontend types`](#/Shared%20frontend%20types) page from the left navigation.

For a quick architecture summary and stable-root exports, keep `doc/Frontend Reusable Surface.md` nearby as the overview page.
