/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import AdminSettings from './views/AdminSettings.vue'

const el = document.getElementById('twofactor-gateway-admin')
if (el) {
	createApp(AdminSettings).mount(el)
}
