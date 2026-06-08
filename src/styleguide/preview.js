/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { gatewayAdminApiKey } from '../lib/twofactor-gateway/composables/useGatewayAdminApi.ts'
import { styleguidePreviewGatewayAdminApi } from './previewGatewayAdminApi.ts'

export default (app) => {
	app.provide(gatewayAdminApiKey, styleguidePreviewGatewayAdminApi)
}
