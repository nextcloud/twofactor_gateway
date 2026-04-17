// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import GatewayInstanceCard from '../../components/GatewayInstanceCard.vue'
import type { FieldDefinition, GatewayInstance } from '../../services/adminGatewayApi.ts'

Object.defineProperty(window, 'matchMedia', {
	writable: true,
	value: vi.fn().mockImplementation(() => ({
		matches: false,
		addEventListener: vi.fn(),
		removeEventListener: vi.fn(),
	})),
})

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string, parameters?: Record<string, string | number>) => {
		if (parameters === undefined) {
			return `tr:${text}`
		}
		return Object.entries(parameters).reduce(
			(translated, [key, value]) => translated.replace(`{${key}}`, String(value)),
			`tr:${text}`,
		)
	},
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: defineComponent({
		props: ['title', 'disabled', 'type'],
		emits: ['click'],
		template: '<button :title="title" :disabled="disabled" type="button" @click="$emit(\'click\', $event)"><slot /><slot name="icon" /></button>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcChip', () => ({
	default: defineComponent({
		props: ['variant', 'text', 'noClose'],
		template: '<span class="nc-chip-test"><slot>{{ text }}</slot></span>',
	}),
}))

vi.mock('vue-material-design-icons/Delete.vue', () => ({ default: defineComponent({ template: '<span />' }) }))
vi.mock('vue-material-design-icons/Pencil.vue', () => ({ default: defineComponent({ template: '<span />' }) }))
vi.mock('vue-material-design-icons/Star.vue', () => ({ default: defineComponent({ template: '<span />' }) }))
vi.mock('vue-material-design-icons/StarOutline.vue', () => ({ default: defineComponent({ template: '<span />' }) }))
vi.mock('vue-material-design-icons/TestTube.vue', () => ({ default: defineComponent({ template: '<span />' }) }))
vi.mock('vue-material-design-icons/Tune.vue', () => ({ default: defineComponent({ template: '<span />' }) }))

const makeInstance = (overrides: Partial<GatewayInstance> = {}): GatewayInstance => ({
	id: 'abc123',
	providerId: 'signal',
	label: 'Test instance',
	default: false,
	createdAt: '2026-01-15T10:00:00+00:00',
	config: {},
	isComplete: true,
	groupIds: [],
	priority: 0,
	...overrides,
})

const fields: FieldDefinition[] = [
	{ field: 'url', prompt: 'URL', default: 'http://localhost:5000', optional: false },
	{ field: 'account', prompt: 'Account', default: '', optional: true },
]

describe('GatewayInstanceCard', () => {
	it('displays the instance label', () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ label: 'My Signal Config' }), fields },
		})
		expect(wrapper.text()).toContain('My Signal Config')
	})

	it('shows the "Default" badge when the instance is the default', () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ default: true }), fields },
		})
		expect(wrapper.text()).toContain('tr:Default')
	})

	it('does not show the "Default" badge for non-default instances', () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ default: false }), fields },
		})
		expect(wrapper.text()).not.toContain('tr:Default')
	})

	it('shows "Configured" badge when instance is complete', () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ isComplete: true }), fields },
		})
		expect(wrapper.text()).toContain('tr:Configured')
	})

	it('shows "Incomplete" badge when instance is not complete', () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ isComplete: false }), fields },
		})
		expect(wrapper.text()).toContain('tr:Incomplete')
	})

	it('hides the "Set as default" button and shows a disabled star for the default instance', () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ default: true }), fields },
		})
		const buttons = wrapper.findAll('button')
		const titlesAndDisabled = buttons.map((b) => ({
			title: b.attributes('title'),
			disabled: b.attributes('disabled'),
		}))
		expect(titlesAndDisabled.some((b) => b.title === 'tr:This is the default instance' && b.disabled !== undefined)).toBe(true)
		expect(titlesAndDisabled.some((b) => b.title === 'tr:Set as default')).toBe(false)
	})

	it('shows the "Set as default" button for non-default instances', () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ default: false }), fields },
		})
		const buttons = wrapper.findAll('button')
		expect(buttons.some((b) => b.attributes('title') === 'tr:Set as default')).toBe(true)
	})

	it('disables the test button when the instance is incomplete', () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ isComplete: false }), fields },
		})
		const testButton = wrapper.findAll('button').find((b) => b.attributes('title') === 'tr:Test this instance')
		expect(testButton?.attributes('disabled')).not.toBeUndefined()
	})

	it('enables the test button when the instance is complete', () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ isComplete: true }), fields },
		})
		const testButton = wrapper.findAll('button').find((b) => b.attributes('title') === 'tr:Test this instance')
		expect(testButton?.attributes('disabled')).toBeUndefined()
	})

	it('emits "edit" with instance id when the edit button is clicked', async () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ id: 'abc123' }), fields },
		})
		const editButton = wrapper.findAll('button').find((b) => b.attributes('title') === 'tr:Edit')
		await editButton?.trigger('click')
		expect(wrapper.emitted('edit')).toEqual([['abc123']])
	})

	it('emits "delete" with instance id when the delete button is clicked', async () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ id: 'abc123' }), fields },
		})
		const deleteButton = wrapper.findAll('button').find((b) => b.attributes('title') === 'tr:Delete')
		await deleteButton?.trigger('click')
		expect(wrapper.emitted('delete')).toEqual([['abc123']])
	})

	it('emits "set-default" with instance id when the set-default button is clicked', async () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ id: 'abc123', default: false }), fields },
		})
		const setDefaultButton = wrapper.findAll('button').find((b) => b.attributes('title') === 'tr:Set as default')
		await setDefaultButton?.trigger('click')
		expect(wrapper.emitted('set-default')).toEqual([['abc123']])
	})

	it('emits "test" with instance id when the test button is clicked', async () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ id: 'abc123', isComplete: true }), fields },
		})
		const testButton = wrapper.findAll('button').find((b) => b.attributes('title') === 'tr:Test this instance')
		await testButton?.trigger('click')
		expect(wrapper.emitted('test')).toEqual([['abc123']])
	})

	it('emits "routing" with instance id when the routing button is clicked', async () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ id: 'abc123' }), fields },
		})
		const routingButton = wrapper.findAll('button').find((b) => b.attributes('title') === 'tr:Routing')
		await routingButton?.trigger('click')
		expect(wrapper.emitted('routing')).toEqual([['abc123']])
	})

	it('hides the routing button when routing is not relevant yet', () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ id: 'abc123' }), fields, showRoutingAction: false },
		})
		const routingButton = wrapper.findAll('button').find((b) => b.attributes('title') === 'tr:Routing')
		expect(routingButton).toBeUndefined()
	})

	it('masks sensitive field values like tokens', () => {
		const instance = makeInstance({
			config: { token: 'mysecrettoken', url: 'http://example.com' },
		})
		const fieldsWithToken: FieldDefinition[] = [
			{ field: 'token', prompt: 'Token', default: '', optional: false },
			{ field: 'url', prompt: 'URL', default: '', optional: false },
		]
		const wrapper = mount(GatewayInstanceCard, { props: { instance, fields: fieldsWithToken } })
		expect(wrapper.text()).toContain('http://example.com')
		expect(wrapper.text()).not.toContain('mysecrettoken')
		expect(wrapper.text()).toContain('••••••••')
	})

	it('does not display hidden metadata fields', () => {
		const instance = makeInstance({
			config: { url: 'http://example.com', session_id: 'abc123' },
		})
		const fieldsWithHidden: FieldDefinition[] = [
			{ field: 'url', prompt: 'URL', default: '', optional: false },
			{ field: 'session_id', prompt: 'Session ID', default: '__INSTANCE_ID__', optional: true, hidden: true },
		]
		const wrapper = mount(GatewayInstanceCard, { props: { instance, fields: fieldsWithHidden } })
		expect(wrapper.text()).toContain('http://example.com')
		expect(wrapper.text()).not.toContain('abc123')
		expect(wrapper.text()).not.toContain('Session ID')
	})

	it('displays routing metadata when groups and priority are configured', () => {
		const instance = makeInstance({ groupIds: ['client-a', 'admins'], priority: 20 })
		const wrapper = mount(GatewayInstanceCard, {
			props: {
				instance,
				fields,
				groups: [
					{ id: 'client-a', displayName: 'Client A' },
					{ id: 'admins', displayName: 'Admins' },
				],
			},
		})

		expect(wrapper.text()).toContain('tr:Groups')
		const groupChips = wrapper.findAll('.routing-group-chip')
		expect(groupChips).toHaveLength(2)
		expect(groupChips[0].text()).toBe('Client A')
		expect(groupChips[1].text()).toBe('Admins')
	})

	it('renders boolean fields with readable labels', () => {
		const instance = makeInstance({
			config: {
				webhook_hybrid_enabled: '1',
				fallback_enabled: '0',
			},
		})
		const booleanFields: FieldDefinition[] = [
			{ field: 'webhook_hybrid_enabled', prompt: 'Hybrid webhook', default: '0', optional: true, type: 'boolean' },
			{ field: 'fallback_enabled', prompt: 'Fallback', default: '0', optional: true, type: 'boolean' },
		]

		const wrapper = mount(GatewayInstanceCard, { props: { instance, fields: booleanFields } })

		expect(wrapper.text()).toContain('tr:Enabled')
		expect(wrapper.text()).toContain('tr:Disabled')
		expect(wrapper.text()).not.toContain('webhook_hybrid_enabled:1')
		expect(wrapper.text()).not.toContain('fallback_enabled:0')
	})

	it('displays the creation date', () => {
		const wrapper = mount(GatewayInstanceCard, {
			props: { instance: makeInstance({ createdAt: '2026-01-15T10:00:00+00:00' }), fields },
		})
		// The t() mock interpolates the formatted date, so we check the prefix is present
		expect(wrapper.text()).toContain('tr:Created:')
	})

	it('handles missing config and groupIds without throwing', () => {
		const instance = {
			...makeInstance(),
			config: undefined,
			groupIds: undefined,
		} as unknown as GatewayInstance

		const wrapper = mount(GatewayInstanceCard, {
			props: { instance, fields },
		})

		expect(wrapper.text()).toContain('Test instance')
		expect(wrapper.text()).not.toContain('tr:Groups:')
	})
})
