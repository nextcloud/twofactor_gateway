// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { GatewayInfo, GatewayInstance } from '../lib/twofactor-gateway/types/gateway.ts'
import { cloneGatewayById } from './mocks/data.ts'
import { resetStyleguidePreviewState } from './previewGatewayAdminApi.ts'

export function resetStyleguideDemoState(): void {
	resetStyleguidePreviewState()
}

export function createStyleguideDemoState<T>(factory: () => T): T {
	resetStyleguidePreviewState()
	return factory()
}

export function createStyleguideGatewayDemo(gatewayId: string): GatewayInfo {
	return createStyleguideDemoState(() => cloneGatewayById(gatewayId))
}

export function createStyleguideGatewayInstanceDemo(gatewayId: string, instanceIndex = 0): {
	gateway: GatewayInfo
	instance: GatewayInstance
} {
	return createStyleguideDemoState(() => {
		const gateway = cloneGatewayById(gatewayId)
		return {
			gateway,
			instance: gateway.instances[instanceIndex],
		}
	})
}
