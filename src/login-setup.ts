/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import LoginSetup from './views/LoginSetup.vue'

const el = document.getElementById('twofactor-gateway-login-setup')
if (el) {
	const gateway = (document.getElementById('twofactor-gateway-login-setup-gateway') as HTMLInputElement | null)?.value ?? ''
	const isComplete = (document.getElementById('twofactor-gateway-login-setup-is-complete') as HTMLInputElement | null)?.value === '1'

	const state = loadState('twofactor_gateway', `settings-${gateway}`, {
		name: '',
	})

	createApp(LoginSetup, {
		gatewayName: gateway,
		displayName: state.name,
		isComplete,
	}).mount(el)
}
