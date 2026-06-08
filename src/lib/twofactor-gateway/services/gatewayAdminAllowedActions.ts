// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { GatewayAdminAllowedActions } from '../types/gateway.ts'

export function resolveGatewayAdminAllowedActions(overrides: Partial<GatewayAdminAllowedActions> | null | undefined = null): GatewayAdminAllowedActions {
	return {
		canView: true,
		canCreateInstances: true,
		canEditInstances: true,
		canDeleteInstances: true,
		canSetDefaultInstances: true,
		canManageRouting: true,
		canTestInstances: true,
		canReorderInstances: true,
		...overrides,
	}
}