// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { ref } from 'vue'
import {
	listGateways,
	listGroups,
	type GatewayGroup,
	type GatewayInfo,
} from '../index.ts'

export function useGatewayApi() {
	const loading = ref(false)
	const error = ref('')
	const gateways = ref<GatewayInfo[]>([])
	const groups = ref<GatewayGroup[]>([])

	async function load() {
		loading.value = true
		error.value = ''
		try {
			const [nextGateways, nextGroups] = await Promise.all([listGateways(), listGroups()])
			gateways.value = nextGateways
			groups.value = nextGroups
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
		load,
	}
}
