<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

This page is the **canonical human-facing explanation** of the non-primitive types exported by `@lib/twofactor-gateway`.

If a component prop table shows names such as `FieldDefinition[]`, `GatewayInfo`, `GatewayGroup[]` or `GatewayAdminInitialData`, look them up here instead of expecting every component page to duplicate the same contract.

## Source of truth

These files remain the technical source of truth for the contracts described below:

- `src/lib/twofactor-gateway/types/gateway.ts`
- `src/lib/twofactor-gateway/services/gatewayAdminSnapshot.ts`
- `src/lib/twofactor-gateway/services/adminGatewayViewModel.ts`

This page exists to explain those contracts in plain language.

## Base gateway schema types

### `FieldDefinition`

Describes **one configurable field** exposed by a gateway or provider.

Typical shape:

```json
{
  "field": "api_token",
  "prompt": "API token",
  "default": "",
  "optional": false,
  "type": "secret",
  "helper": "Paste the provider token created in the dashboard"
}
```

Most important keys:

- `field`: stable machine key stored in `config`
- `prompt`: human-readable label shown in the UI
- `default`: default string value
- `optional`: whether the field may be left empty
- `type`: optional rendering hint such as `secret`, `boolean` or `integer`
- `helper`: optional help text shown to the user
- `hidden`: field exists in schema/config flows but should not be rendered in summary UIs
- `min` / `max`: optional numeric limits for integer-like inputs

### `GatewayGroup`

Represents **one selectable Nextcloud group option** used for routing scope.

Typical shape:

```json
{
  "id": "admins",
  "displayName": "Admins"
}
```

### `GatewayInstance`

Represents **one saved provider configuration instance**.

It includes:

- stable id
- selected provider id
- human label
- raw config values
- routing `groupIds`
- routing `priority`
- completion/default status

### `GatewayProviderDefinition`

Represents **one provider option inside a multi-provider gateway**.

Typical example: a WhatsApp gateway exposing multiple provider choices, each with its own field schema.

### `GatewayInfo`

Represents **one gateway definition coming from the backend**.

It includes:

- gateway metadata (`id`, `name`, `instructions`)
- base field schema (`fields`)
- optional provider selector / provider catalog
- configured instances (`instances`)

## Admin screen payload types

### `GatewayAdminSnapshot`

This is the **transport-level admin payload**.

It can come from:

- Nextcloud initial state on the first render
- `GET /ocs/v2.php/apps/twofactor_gateway/admin/screen`

It is the input of `normalizeGatewayAdminSnapshot(...)`.

### `GatewayAdminInitialData`

This is the **normalized payload consumed by `GatewayAdminSettings`**.

Think of it as the final frontend contract after transport normalization.

Top-level keys:

- `gateways`: raw gateway catalog and instances
- `groups`: routing groups selectable in the UI
- `items`: ready-to-render list rows
- `allowedActions`: optional UI action restrictions

### `FlatInstanceEntry`

This is **one screen-ready row** rendered in the admin UI.

It combines:

- gateway identity
- provider display name
- active field schema for the selected provider
- normalized instance data
- resolved `groupNames`
- `showRoutingAction`

In other words: it is not just raw backend data; it is the row model used by the UI.

### `GatewayAdminAllowedActions`

These are **UI action flags** used by a host app or backend-aware integration to hide or show actions.

Examples:

- `canView`
- `canCreateInstances`
- `canEditInstances`
- `canDeleteInstances`
- `canManageRouting`
- `canTestInstances`

They narrow the visible UI, but the backend remains the source of truth for permission enforcement.

## OpenAPI vs frontend-only contracts

| Contract | Should be in OpenAPI? | Why |
| --- | --- | --- |
| `FieldDefinition` | Yes, when it is part of an HTTP response schema. | It is transport data when returned by backend endpoints. |
| `GatewayGroup` | Yes. | Returned by backend endpoints such as `/admin/groups` and `/admin/screen`. |
| `GatewayInfo` | Yes. | Returned by backend endpoints such as `/admin/gateways` and `/admin/screen`. |
| `GatewayAdminSnapshot` | Partly. | Its HTTP form belongs in OpenAPI; the embedded initial-state bootstrap does not, because it is not an HTTP endpoint. |
| `FlatInstanceEntry` | Partly. | The `/admin/screen` HTTP payload may expose the same fields, but the type itself is primarily a frontend render model. |
| `GatewayAdminInitialData` | No. | This is the normalized frontend contract after transport processing. |
| `GatewayAdminAllowedActions` | Partly. | Backend-returned action-visibility fields belong in transport docs; the standalone prop type is still frontend-facing. |

So the rule of thumb is:

- **OpenAPI** documents backend transport payloads.
- **This page** documents the reusable frontend contracts developers actually need to understand while integrating components.
