// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { FieldDefinition, GatewayGroup, GatewayInfo, GatewayInstance } from '../types/gateway.ts'

export interface FlatInstanceEntry {
	orderKey: string
	gatewayId: string
	providerName: string
	fields: FieldDefinition[]
	instance: GatewayInstance
	groupNames?: string[]
	showRoutingAction: boolean
}

function resolveGroupNames(groupIds: string[], groups: GatewayGroup[]): string[] {
	if (groupIds.length === 0) {
		return []
	}

	return groupIds
		.map((groupId) => groups.find((group) => group.id === groupId)?.displayName ?? groupId)
		.filter((groupName, index, groupNames) => groupName !== '' && groupNames.indexOf(groupName) === index)
}

export function buildFlatInstances(gateways: GatewayInfo[], groups: GatewayGroup[] = []): FlatInstanceEntry[] {
	const rows: FlatInstanceEntry[] = []
	for (const gateway of gateways) {
		const instances = Array.isArray(gateway.instances) ? gateway.instances : []
		for (const instance of instances) {
			const groupIds = Array.isArray(instance.groupIds) ? instance.groupIds : []
			const priority = typeof instance.priority === 'number' ? instance.priority : 0
			const selectedProviderId = gateway.providerSelector
				? instance.config[gateway.providerSelector.field]
				: undefined
			const selectedProvider = gateway.providerCatalog?.find((provider) => provider.id === selectedProviderId)

			rows.push({
				orderKey: `${gateway.id}:${instance.id}`,
				gatewayId: gateway.id,
				providerName: selectedProvider?.name ?? gateway.name,
				fields: selectedProvider?.fields ?? gateway.fields,
				instance: {
					...instance,
					groupIds,
					priority,
				},
				groupNames: resolveGroupNames(groupIds, groups),
				showRoutingAction: true,
			})
		}
	}

	return rows.sort((left, right) => {
		const priorityDiff = (right.instance.priority ?? 0) - (left.instance.priority ?? 0)
		if (priorityDiff !== 0) {
			return priorityDiff
		}

		const labelDiff = left.instance.label.localeCompare(right.instance.label)
		if (labelDiff !== 0) {
			return labelDiff
		}

		return left.orderKey.localeCompare(right.orderKey)
	})
}

export function mergeOrderKeys(existingOrderKeys: string[], items: FlatInstanceEntry[]): string[] {
	const nextKeys = items.map((item) => item.orderKey)
	const nextKeysSet = new Set(nextKeys)
	const keptKeys = existingOrderKeys.filter((key) => nextKeysSet.has(key))
	const appendedKeys = nextKeys.filter((key) => !keptKeys.includes(key))
	return [...keptKeys, ...appendedKeys]
}

export function orderInstances(items: FlatInstanceEntry[], orderKeys: string[]): FlatInstanceEntry[] {
	const fallbackOrder = items.map((item) => item.orderKey)
	const knownOrder = orderKeys.length > 0 ? orderKeys : fallbackOrder
	const position = new Map(knownOrder.map((key, index) => [key, index]))

	return [...items].sort((left, right) => {
		const leftIndex = position.get(left.orderKey) ?? Number.MAX_SAFE_INTEGER
		const rightIndex = position.get(right.orderKey) ?? Number.MAX_SAFE_INTEGER
		return leftIndex - rightIndex
	})
}

export function findFlatInstanceEntry(items: FlatInstanceEntry[], gatewayId: string, instanceId: string): FlatInstanceEntry | undefined {
	return items.find((entry) => entry.gatewayId === gatewayId && entry.instance.id === instanceId)
		?? items.find((entry) => entry.instance.id === instanceId)
}

export function buildPriorityUpdates(orderedItems: FlatInstanceEntry[]): Array<{ item: FlatInstanceEntry; priority: number }> {
	return orderedItems
		.map((item, index) => ({
			item,
			priority: orderedItems.length - index,
		}))
		.filter(({ item, priority }) => item.instance.priority !== priority)
}
