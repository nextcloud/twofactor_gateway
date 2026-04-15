// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import AdminSettings from '../../views/AdminSettings.vue'
import type { GatewayInfo } from '../../services/adminGatewayApi.ts'

const GatewayInstanceCardStub = vi.hoisted(() => ({
	name: 'GatewayInstanceCard',
	props: ['instance', 'providerName'],
	template: '<div class="gateway-instance-card" :data-provider="providerName">{{ instance.label }}</div>',
}))

const GatewayInstanceModalStub = vi.hoisted(() => ({
	name: 'GatewayInstanceModal',
	props: ['show'],
	template: '<div class="gateway-instance-modal" :data-show="show" />',
}))

const GatewayTestModalStub = vi.hoisted(() => ({
	name: 'GatewayTestModal',
	props: ['show'],
	template: '<div class="gateway-test-modal" :data-show="show" />',
}))

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

vi.mock('../../services/adminGatewayApi.ts', () => ({
	listGateways: vi.fn().mockResolvedValue([]),
	createInstance: vi.fn(),
	updateInstance: vi.fn(),
	deleteInstance: vi.fn(),
	setDefaultInstance: vi.fn(),
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: defineComponent({
		emits: ['click'],
		template: '<button type="button" @click="$emit(\'click\', $event)"><slot /></button>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({
	default: defineComponent({
		props: ['name', 'description'],
		template: '<div class="nc-empty-content"><span class="name">{{ name }}</span><span class="description">{{ description }}</span><slot name="action" /></div>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
	default: defineComponent({ template: '<div class="nc-loading-icon" />' }),
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: defineComponent({
		props: ['open', 'name', 'message'],
		template: '<div v-if="open" class="nc-dialog"><span class="message">{{ message }}</span><slot name="actions" /></div>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcSettingsSection', () => ({
	default: defineComponent({
		props: ['name', 'description', 'docUrl'],
		template: '<div class="settings-section"><slot /></div>',
	}),
}))


vi.mock('vue-material-design-icons/AlertCircle.vue', () => ({
	default: defineComponent({ template: '<span class="alert-circle-icon" />' }),
}))

vi.mock('../../components/GatewayInstanceCard.vue', () => ({
	default: GatewayInstanceCardStub,
}))

vi.mock('../../components/GatewayInstanceModal.vue', () => ({
	default: GatewayInstanceModalStub,
}))

vi.mock('../../components/GatewayTestModal.vue', () => ({
	default: GatewayTestModalStub,
}))

describe('AdminSettings', () => {
	it('shows a loading indicator while fetching gateways', async () => {
		const { listGateways } = await import('../../services/adminGatewayApi.ts')
		let resolveGateways: (value: GatewayInfo[]) => void = () => {}
		vi.mocked(listGateways).mockReturnValueOnce(
			new Promise<GatewayInfo[]>((resolve) => { resolveGateways = resolve }),
		)

		const wrapper = mount(AdminSettings)

		// Loading icon should be visible before data arrives
		expect(wrapper.find('.nc-loading-icon').exists()).toBe(true)
		resolveGateways([])
		await flushPromises()
		expect(wrapper.find('.nc-loading-icon').exists()).toBe(false)
	})

	it('renders a unified instance list without provider rows', async () => {
		const { listGateways } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(listGateways).mockResolvedValueOnce([
			{
				id: 'signal',
				name: 'Signal',
				instructions: '',
				allowMarkdown: false,
				fields: [],
				instances: [{ id: 's1', label: 'Signal Prod', default: true, createdAt: '', config: {}, isComplete: true }],
			},
			{
				id: 'telegram',
				name: 'Telegram',
				instructions: '',
				allowMarkdown: false,
				fields: [],
				instances: [{ id: 't1', label: 'Telegram Ops', default: false, createdAt: '', config: {}, isComplete: true }],
			},
		])

		const wrapper = mount(AdminSettings)
		await flushPromises()

		const cards = wrapper.findAll('.gateway-instance-card')
		expect(cards).toHaveLength(2)
		expect(cards[0].attributes('data-provider')).toBe('Signal')
		expect(cards[1].attributes('data-provider')).toBe('Telegram')
	})

	it('shows an error state when the API call fails', async () => {
		const { listGateways } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(listGateways).mockRejectedValueOnce(new Error('Network error'))

		const wrapper = mount(AdminSettings)
		await flushPromises()

		expect(wrapper.find('.nc-empty-content').exists()).toBe(true)
		expect(wrapper.find('.nc-empty-content .name').text()).toContain('tr:Failed to load gateways')
	})

	it('retries loading when the retry button is clicked after an error', async () => {
		const { listGateways } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(listGateways)
			.mockRejectedValueOnce(new Error('First error'))
			.mockResolvedValueOnce([
				{
					id: 'signal',
					name: 'Signal',
					instructions: '',
					allowMarkdown: false,
					fields: [],
					instances: [{ id: 's1', label: 'Signal Prod', default: true, createdAt: '', config: {}, isComplete: true }],
				},
			])

		const wrapper = mount(AdminSettings)
		await flushPromises()

		// Error state shown
		expect(wrapper.find('.nc-empty-content').exists()).toBe(true)

		// Click retry
		await wrapper.find('button').trigger('click')
		await flushPromises()

		// Now the unified list renders cards
		expect(wrapper.find('.nc-empty-content').exists()).toBe(false)
		expect(wrapper.findAll('.gateway-instance-card')).toHaveLength(1)
	})

	it('opens create modal from the single add button', async () => {
		const { listGateways } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(listGateways).mockResolvedValueOnce([])

		const wrapper = mount(AdminSettings)
		await flushPromises()

		const addButton = wrapper.findAll('button').find((button) => button.text().includes('tr:Add provider configuration'))
		expect(addButton).toBeDefined()
		await addButton?.trigger('click')

		expect(wrapper.findComponent({ name: 'GatewayInstanceModal' }).props('show')).toBe(true)
	})

	it('uses catalog provider name for flattened instances', async () => {
		const { listGateways } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(listGateways).mockResolvedValueOnce([
			{
				id: 'whatsapp',
				name: 'WhatsApp',
				instructions: '',
				allowMarkdown: false,
				fields: [],
				providerSelector: { field: 'provider', prompt: 'Provider', default: '', optional: false },
				providerCatalog: [
					{ id: 'whatsapp', name: 'WhatsApp', fields: [] },
					{ id: 'gowhatsapp', name: 'WhatsApp', fields: [] },
				],
				instances: [{ id: 'gowhatsapp:1', label: 'Default', default: true, createdAt: '', config: { provider: 'gowhatsapp' }, isComplete: true }],
			},
		])

		const wrapper = mount(AdminSettings)
		await flushPromises()

		const cards = wrapper.findAll('.gateway-instance-card')
		expect(cards).toHaveLength(1)
		expect(cards[0].attributes('data-provider')).toBe('WhatsApp')
	})

	it('resolves edit target by emitted instance id', async () => {
		const { listGateways } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(listGateways).mockResolvedValueOnce([
			{
				id: 'whatsapp',
				name: 'WhatsApp',
				instructions: '',
				allowMarkdown: false,
				fields: [],
				providerSelector: { field: 'provider', prompt: 'Provider', default: '', optional: false },
				providerCatalog: [
					{ id: 'whatsapp', name: 'WhatsApp', fields: [] },
					{ id: 'gowhatsapp', name: 'WhatsApp', fields: [] },
				],
				instances: [{ id: 'gowhatsapp:1', label: 'Default', default: true, createdAt: '', config: { provider: 'gowhatsapp' }, isComplete: true }],
			},
		])

		const wrapper = mount(AdminSettings)
		await flushPromises()

		;(wrapper.vm as unknown as {
			openEditById: (gatewayId: string, instanceId: string) => void
			editingGatewayId: string
			editingInstanceId: string
			showModal: boolean
		}).openEditById('whatsapp', 'gowhatsapp:1')

		expect((wrapper.vm as { editingGatewayId: string }).editingGatewayId).toBe('whatsapp')
		expect((wrapper.vm as { editingInstanceId: string }).editingInstanceId).toBe('gowhatsapp:1')
		expect((wrapper.vm as { showModal: boolean }).showModal).toBe(true)
	})
})
