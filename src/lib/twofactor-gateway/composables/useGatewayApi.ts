// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { ref } from 'vue'
import {
	type GatewayGroup,
	type GatewayInfo,
	type FlatInstanceEntry,
} from '../index.ts'
import { useGatewayAdminApi, type GatewayAdminApi } from './useGatewayAdminApi.ts'

export function useGatewayApi(gatewayAdminApi: GatewayAdminApi = useGatewayAdminApi()) {
	const loading = ref(false)
	const error = ref('')
	const gateways = ref<GatewayInfo[]>([])
	const groups = ref<GatewayGroup[]>([])
	const items = ref<FlatInstanceEntry[]>([])

	async function load() {
		loading.value = true
		error.value = ''
		try {
			const screen = await gatewayAdminApi.listAdminScreen()
			gateways.value = screen.gateways
			groups.value = screen.groups
			items.value = screen.items
		} catch (e) {
			error.value = e instanceof Error ? e.message : 'Failed to load gateways'
		} finally {
			loading.value = false
		}
	}

	return {
		loading,
		error,
		gateways,
		groups,
		items,
		load,
	}
}
