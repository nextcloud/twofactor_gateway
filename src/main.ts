/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import GatewaySettings from './views/GatewaySettings.vue'

const MOUNT_PREFIX = 'twofactor-gateway-'

document.querySelectorAll<HTMLElement>(`div[id^="${MOUNT_PREFIX}"]`).forEach((el) => {
	const provider = el.id.slice(MOUNT_PREFIX.length)

	const state = loadState('twofactor_gateway', `settings-${provider}`, {
		name: '',
		instructions: '',
	})

	createApp(GatewaySettings, {
		gatewayName: provider,
		displayName: state.name,
		instructions: state.instructions || '',
	}).mount(el)
})
