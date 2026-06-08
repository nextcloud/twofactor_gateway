<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

These pages document the reusable UI surface exported through `@lib/twofactor-gateway/components/*`.

## Coupling levels

### Containers

Use these when another app intentionally wants to reuse Two Factor Gateway's own loading and mutation workflows.

- `GatewayAdminSettings`
- `GatewaySection`

### Reusable building blocks

Use these when another app wants lower-level UI pieces and keeps more orchestration control in the parent app.

- `GatewayInstanceCard`
- `GatewayInstanceModal`
- `GatewayRoutingModal`
- `GatewayTestModal`

## Import style

Prefer the narrowest stable entrypoint that matches the component you actually need.

```js static
import { GatewayInstanceCard } from '@lib/twofactor-gateway/components/gatewayInstanceCard'
import { GatewayRoutingModal } from '@lib/twofactor-gateway/components/gatewayRoutingModal'
```

The grouped barrel `@lib/twofactor-gateway/components/gatewayManagement` remains available for convenience, but the narrower imports are the safer default for runtime and test consumers.

## Live previews

The component pages in this styleguide use in-memory demo data and a documentation-only mock backend.

That means you can open modals, trigger emitted events and inspect the overall interaction flow without needing a running Nextcloud instance behind the scenes.
