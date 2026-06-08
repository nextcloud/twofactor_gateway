<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

The root entrypoint `@lib/twofactor-gateway` exposes the stable non-visual contracts already reused by the app itself.

```ts static
import {
  listGateways,
  listGroups,
  buildFlatInstances,
  useGatewayApi,
  type GatewayInfo,
  type GatewayInstance,
} from '@lib/twofactor-gateway'
```

## Shared types

Human-facing explanations of exported non-primitive types now live in the dedicated
[`Shared frontend types`](#/Shared%20frontend%20types) page.

Use that page whenever a component prop table shows names such as `FieldDefinition`,
`GatewayInfo`, `GatewayGroup`, `GatewayAdminInitialData` or `FlatInstanceEntry`.

## Admin API helpers

- `listGateways`
- `listGroups`
- `createInstance`
- `getInstance`
- `updateInstance`
- `deleteInstance`
- `setDefaultInstance`
- `testInstance`
- `startInteractiveSetup`
- `interactiveSetupStep`
- `cancelInteractiveSetup`

## View-model helpers

- `buildFlatInstances`
- `mergeOrderKeys`
- `orderInstances`
- `findFlatInstanceEntry`
- `buildPriorityUpdates`

## Gateway modal model helpers

- `resolveGatewayId`
- `normalizeProviderCatalog`
- `resolveEffectiveCatalogProviderId`
- `resolveCurrentFields`
- `resolveVisibleFields`
- `resolveFieldsToValidate`
- `canUseGuidedSetupPanel`
- `computeCatalogSelectionState`
- `validateGatewayInstanceForm`

## Composables

- `useGatewayApi`
- `useGatewayForm`

## Consumer guidance

- Use the root barrel for **shared contracts and orchestration helpers**
- Use component entrypoints for **stable UI blocks**
- If another app wants low coupling, start with the types, view-model helpers and the lower-level components before adopting the app-level containers
