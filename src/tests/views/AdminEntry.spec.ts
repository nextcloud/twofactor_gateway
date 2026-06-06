// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { beforeEach, describe, expect, it, vi } from 'vitest'

const { createAppMock, mountMock, gatewayAdminSettingsStub } = vi.hoisted(() => {
	const mount = vi.fn()

	return {
		createAppMock: vi.fn(() => ({ mount })),
		mountMock: mount,
		gatewayAdminSettingsStub: { name: 'GatewayAdminSettingsStub' },
	}
})

vi.mock('vue', () => ({
	createApp: createAppMock,
}))

vi.mock('@lib/twofactor-gateway/components', () => ({
	GatewayAdminSettings: gatewayAdminSettingsStub,
}))

describe('admin entrypoint', () => {
	beforeEach(() => {
		vi.resetModules()
		vi.clearAllMocks()
		document.body.innerHTML = ''
	})

	it('mounts the stable gateway admin settings surface when the admin root exists', async () => {
		document.body.innerHTML = '<div id="twofactor-gateway-admin"></div>'

		await import('../../admin.ts')

		const adminRoot = document.getElementById('twofactor-gateway-admin')

		expect(createAppMock).toHaveBeenCalledWith(gatewayAdminSettingsStub)
		expect(mountMock).toHaveBeenCalledWith(adminRoot)
	})

	it('does not mount anything when the admin root is missing', async () => {
		await import('../../admin.ts')

		expect(createAppMock).not.toHaveBeenCalled()
		expect(mountMock).not.toHaveBeenCalled()
	})
})