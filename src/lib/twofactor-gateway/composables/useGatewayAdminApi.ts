// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { inject, provide, type InjectionKey } from 'vue'
import {
	cancelInteractiveSetup,
	createInstance,
	deleteInstance,
	getInstance,
	interactiveSetupStep,
	listAdminScreen,
	listGateways,
	listGroups,
	setDefaultInstance,
	startInteractiveSetup,
	testInstance,
	updateInstance,
} from '../services/adminGatewayApi.ts'
import { normalizeGatewayAdminSnapshot } from '../services/gatewayAdminSnapshot.ts'

export type GatewayAdminApi = {
	cancelInteractiveSetup: typeof cancelInteractiveSetup
	createInstance: typeof createInstance
	deleteInstance: typeof deleteInstance
	getInstance: typeof getInstance
	interactiveSetupStep: typeof interactiveSetupStep
	listAdminScreen: typeof listAdminScreen
	listGateways: typeof listGateways
	listGroups: typeof listGroups
	setDefaultInstance: typeof setDefaultInstance
	startInteractiveSetup: typeof startInteractiveSetup
	testInstance: typeof testInstance
	updateInstance: typeof updateInstance
}

export const defaultGatewayAdminApi: GatewayAdminApi = {
	cancelInteractiveSetup,
	createInstance,
	deleteInstance,
	getInstance,
	interactiveSetupStep,
	listAdminScreen,
	listGateways,
	listGroups,
	setDefaultInstance,
	startInteractiveSetup,
	testInstance,
	updateInstance,
}

export const gatewayAdminApiKey: InjectionKey<GatewayAdminApi> = Symbol('twofactor-gateway-admin-api')

export function createGatewayAdminApi(overrides: Partial<GatewayAdminApi> = {}): GatewayAdminApi {
	const api: GatewayAdminApi = {
		...defaultGatewayAdminApi,
		...overrides,
	}

	if (overrides.listAdminScreen === undefined) {
		api.listAdminScreen = async (groupLimit = 200) => {
			const [gateways, groups] = await Promise.all([
				api.listGateways(),
				api.listGroups('', groupLimit),
			])
			const normalized = normalizeGatewayAdminSnapshot({ gateways, groups })
			if (normalized === null) {
				throw new Error('Unexpected empty admin screen payload')
			}

			return normalized
		}
	}

	return api
}

export function provideGatewayAdminApi(api: GatewayAdminApi): GatewayAdminApi {
	provide(gatewayAdminApiKey, api)
	return api
}

export function useGatewayAdminApi(): GatewayAdminApi {
	return inject(gatewayAdminApiKey, defaultGatewayAdminApi)
}
