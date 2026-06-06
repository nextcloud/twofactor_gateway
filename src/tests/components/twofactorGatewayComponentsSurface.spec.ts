// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'

const gatewayAdminSettingsStub = { name: 'GatewayAdminSettingsStub' }
const gatewayInstanceCardStub = { name: 'GatewayInstanceCardStub' }
const gatewayInstanceModalStub = { name: 'GatewayInstanceModalStub' }
const gatewayRoutingModalStub = { name: 'GatewayRoutingModalStub' }
const gatewaySectionStub = { name: 'GatewaySectionStub' }
const gatewayTestModalStub = { name: 'GatewayTestModalStub' }

vi.mock('../../views/AdminSettings.vue', () => ({
	default: gatewayAdminSettingsStub,
}))

vi.mock('../../components/GatewayInstanceCard.vue', () => ({
	default: gatewayInstanceCardStub,
}))

vi.mock('../../components/GatewayInstanceModal.vue', () => ({
	default: gatewayInstanceModalStub,
}))

vi.mock('../../components/GatewayRoutingModal.vue', () => ({
	default: gatewayRoutingModalStub,
}))

vi.mock('../../components/GatewaySection.vue', () => ({
	default: gatewaySectionStub,
}))

vi.mock('../../components/GatewayTestModal.vue', () => ({
	default: gatewayTestModalStub,
}))

describe('twofactor gateway component surface', () => {
	it('exports the stable reusable admin components', async () => {
		const components = await import('@lib/twofactor-gateway/components')

		expect(components.GatewayAdminSettings).toBe(gatewayAdminSettingsStub)
		expect(components.GatewayInstanceCard).toBe(gatewayInstanceCardStub)
		expect(components.GatewayInstanceModal).toBe(gatewayInstanceModalStub)
		expect(components.GatewayRoutingModal).toBe(gatewayRoutingModalStub)
		expect(components.GatewaySection).toBe(gatewaySectionStub)
		expect(components.GatewayTestModal).toBe(gatewayTestModalStub)
	})
})