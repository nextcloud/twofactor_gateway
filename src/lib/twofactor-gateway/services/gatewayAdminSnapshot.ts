// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type {
	GatewayAdminAllowedActions,
	GatewayGroup,
	GatewayInfo,
	GatewayInstancePayload,
} from '../types/gateway.ts'
import { normalizeGatewayInstance } from '../types/gateway.ts'
import { buildFlatInstances, type FlatInstanceEntry } from './adminGatewayViewModel.ts'
import { resolveGatewayAdminAllowedActions } from './gatewayAdminAllowedActions.ts'

export type GatewayInfoSnapshot = Omit<GatewayInfo, 'instances'> & {
	instances?: GatewayInstancePayload[]
}

export type GatewayAdminSnapshot = {
	gateways?: GatewayInfoSnapshot[]
	groups?: GatewayGroup[]
	items?: Array<Omit<FlatInstanceEntry, 'instance'> & { instance: GatewayInstancePayload }>
	allowedActions?: Partial<GatewayAdminAllowedActions>
}

export type GatewayAdminInitialData = {
	gateways: GatewayInfo[]
	groups: GatewayGroup[]
	items: FlatInstanceEntry[]
	allowedActions?: GatewayAdminAllowedActions
}

function clone<T>(value: T): T {
	return JSON.parse(JSON.stringify(value)) as T
}

export function normalizeGatewayAdminSnapshot(snapshot: GatewayAdminSnapshot | null | undefined): GatewayAdminInitialData | null {
	if (!snapshot) {
		return null
	}

	const normalizedGateways = (snapshot.gateways ?? []).map((gateway) => ({
		...gateway,
		instances: (gateway.instances ?? []).map((instance) => normalizeGatewayInstance(instance, gateway.id)),
	}))
	const normalizedGroups = Array.isArray(snapshot.groups) ? clone(snapshot.groups) : []
	const resolvedAllowedActions = resolveGatewayAdminAllowedActions(snapshot.allowedActions)

	return {
		gateways: normalizedGateways,
		groups: normalizedGroups,
		items: Array.isArray(snapshot.items)
			? snapshot.items.map((item) => ({
				...item,
				instance: normalizeGatewayInstance(item.instance, item.gatewayId),
				groupNames: Array.isArray(item.groupNames) ? clone(item.groupNames) : resolveGroupNamesFromState(item.instance.groupIds, normalizedGroups),
			}))
			: buildFlatInstances(normalizedGateways, normalizedGroups),
		allowedActions: resolvedAllowedActions,
	}
}

function resolveGroupNamesFromState(groupIds: string[] | undefined, groups: GatewayGroup[]): string[] {
	if (!Array.isArray(groupIds) || groupIds.length === 0) {
		return []
	}

	return groupIds
		.map((groupId) => groups.find((group) => group.id === groupId)?.displayName ?? groupId)
		.filter((groupName, index, groupNames) => groupName !== '' && groupNames.indexOf(groupName) === index)
}
