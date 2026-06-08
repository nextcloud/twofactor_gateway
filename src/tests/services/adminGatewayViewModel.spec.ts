// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it } from 'vitest'
import {
	buildFlatInstances,
	buildPriorityUpdates,
	findFlatInstanceEntry,
	mergeOrderKeys,
	orderInstances,
	type FlatInstanceEntry,
} from '@lib/twofactor-gateway'
import type { GatewayInfo, GatewayInstance } from '@lib/twofactor-gateway'

const makeInstance = (overrides: Partial<GatewayInstance> = {}): GatewayInstance => ({
	id: 'instance-1',
	providerId: 'signal',
	label: 'Default',
	default: true,
	createdAt: '',
	config: {},
	isComplete: true,
	groupIds: [],
	priority: 0,
	...overrides,
})

describe('adminGatewayViewModel', () => {
	it('buildFlatInstances normalizes provider display, group names and priority sorting', () => {
		const gateways: GatewayInfo[] = [
			{
				id: 'whatsapp',
				name: 'WhatsApp',
				instructions: '',
				allowMarkdown: false,
				fields: [],
				providerSelector: { field: 'provider', prompt: 'Provider', default: '', optional: false },
				providerCatalog: [
					{ id: 'whatsapp', name: 'WhatsApp Cloud', fields: [] },
					{ id: 'gowhatsapp', name: 'Go WhatsApp', fields: [] },
				],
				instances: [
					makeInstance({ id: 'gw-2', label: 'Second', priority: 1, providerId: 'gowhatsapp', config: { provider: 'gowhatsapp' } }),
					makeInstance({ id: 'gw-1', label: 'First', priority: 2, providerId: 'whatsapp', config: { provider: 'whatsapp' }, groupIds: ['admins'] }),
				],
			},
		]

		const rows = buildFlatInstances(gateways, [{ id: 'admins', displayName: 'Admins' }])

		expect(rows).toHaveLength(2)
		expect(rows[0].orderKey).toBe('whatsapp:gw-1')
		expect(rows[0].providerName).toBe('WhatsApp Cloud')
		expect(rows[0].groupNames).toEqual(['Admins'])
		expect(rows[1].providerName).toBe('Go WhatsApp')
	})

	it('mergeOrderKeys keeps existing order and appends new keys', () => {
		const items = [
			{ orderKey: 'a:1' },
			{ orderKey: 'b:1' },
			{ orderKey: 'c:1' },
		] as FlatInstanceEntry[]

		expect(mergeOrderKeys(['b:1', 'a:1'], items)).toEqual(['b:1', 'a:1', 'c:1'])
	})

	it('orderInstances follows known order keys first', () => {
		const items = [
			{ orderKey: 'a:1' },
			{ orderKey: 'b:1' },
			{ orderKey: 'c:1' },
		] as FlatInstanceEntry[]

		const ordered = orderInstances(items, ['c:1', 'a:1'])
		expect(ordered.map((item) => item.orderKey)).toEqual(['c:1', 'a:1', 'b:1'])
	})

	it('findFlatInstanceEntry resolves by gateway + instance then falls back to instance only', () => {
		const items = [
			{ gatewayId: 'signal', instance: { id: 's-1' } },
			{ gatewayId: 'telegram', instance: { id: 't-1' } },
		] as FlatInstanceEntry[]

		expect(findFlatInstanceEntry(items, 'signal', 's-1')?.gatewayId).toBe('signal')
		expect(findFlatInstanceEntry(items, 'unknown', 't-1')?.gatewayId).toBe('telegram')
		expect(findFlatInstanceEntry(items, 'unknown', 'none')).toBeUndefined()
	})

	it('buildPriorityUpdates only returns changed priorities', () => {
		const ordered = [
			{ instance: { priority: 1 } },
			{ instance: { priority: 2 } },
			{ instance: { priority: 3 } },
		] as FlatInstanceEntry[]

		const updates = buildPriorityUpdates(ordered)
		expect(updates).toHaveLength(2)
		expect(updates[0].priority).toBe(3)
		expect(updates[1].priority).toBe(1)
	})
})
