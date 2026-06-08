// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { createLocalGatewayAdminApi } from '../lib/twofactor-gateway/services/localGatewayAdminApi.ts'
import { cloneStyleguideGateways, cloneStyleguideGroups } from './mocks/data.ts'

const styleguidePreview = createLocalGatewayAdminApi({
	createSnapshot: () => ({
		gateways: cloneStyleguideGateways(),
		groups: cloneStyleguideGroups(),
	}),
	testInstance: ({ gatewayId, instanceId, identifier }) => {
		const trimmed = identifier.trim()
		if (trimmed.toLowerCase().includes('fail')) {
			return {
				success: false,
				message: `Preview mock: the test for ${gatewayId}/${instanceId} failed intentionally for identifier "${trimmed}".`,
			}
		}

		return {
			success: true,
			message: `Preview mock: sent a verification message to ${trimmed} using ${gatewayId}/${instanceId}.`,
			accountInfo: {
				account_name: trimmed,
				account_avatar_url: '',
			},
		}
	},
	startInteractiveSetup: ({ sessionId, config }) => ({
		status: 'needs_input',
		sessionId,
		step: 'styleguide-preview',
		message: 'Styleguide preview mock: guided setup is simulated with in-memory data.',
		messageType: 'info',
		config,
	}),
	interactiveSetupStep: ({ action, config, sessionId }) => ({
		status: 'done',
		sessionId,
		step: action,
		message: `Styleguide preview mock: completed action "${action}".`,
		messageType: 'success',
		config,
	}),
	cancelInteractiveSetup: ({ sessionId, config }) => ({
		status: 'cancelled',
		sessionId,
		message: 'Styleguide preview mock: guided setup cancelled.',
		messageType: 'warning',
		config: config ?? {},
	}),
})

export const styleguidePreviewGatewayAdminApi = styleguidePreview.api

export function resetStyleguidePreviewState(): void {
	styleguidePreview.reset()
}
