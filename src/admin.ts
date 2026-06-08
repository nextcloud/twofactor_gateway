/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import {
	normalizeGatewayAdminSnapshot,
	type GatewayAdminSnapshot,
} from '@lib/twofactor-gateway'
import { GatewayAdminSettings } from '@lib/twofactor-gateway/components/adminSettings'

const el = document.getElementById('twofactor-gateway-admin')
if (el) {
	// The backend may hydrate the first admin render through Nextcloud initial state.
	// We only use that snapshot to seed component props; runtime writes still go through
	// the injected live admin API inside GatewayAdminSettings.
	const initialSnapshot = loadState<GatewayAdminSnapshot | null>('twofactor_gateway', 'admin-settings', null)
	const initialData = normalizeGatewayAdminSnapshot(initialSnapshot)

	createApp(GatewayAdminSettings, {
		initialData,
	}).mount(el)
}
