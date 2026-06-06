/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { GatewayAdminSettings } from '@lib/twofactor-gateway/components'

const el = document.getElementById('twofactor-gateway-admin')
if (el) {
	createApp(GatewayAdminSettings).mount(el)
}
