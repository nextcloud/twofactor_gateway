// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import AdminSettings from '../../views/AdminSettings.vue'
import type { GatewayInfo } from '../../services/adminGatewayApi.ts'

const GatewaySectionStub = vi.hoisted(() => ({
	name: 'GatewaySection',
	props: ['gateway'],
	emits: ['updated'],
	template: '<div class="gateway-section" :data-gateway-id="gateway.id">{{ gateway.name }}</div>',
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

vi.mock('vue-material-design-icons/AlertCircle.vue', () => ({
	default: defineComponent({ template: '<span class="alert-circle-icon" />' }),
}))

vi.mock('../../components/GatewaySection.vue', () => ({
	default: GatewaySectionStub,
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

	it('renders a GatewaySection for each gateway returned by the API', async () => {
		const { listGateways } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(listGateways).mockResolvedValueOnce([
			{ id: 'signal', name: 'Signal', instructions: '', allowMarkdown: false, fields: [], instances: [] },
			{ id: 'telegram', name: 'Telegram', instructions: '', allowMarkdown: false, fields: [], instances: [] },
		])

		const wrapper = mount(AdminSettings)
		await flushPromises()

		const sections = wrapper.findAll('.gateway-section')
		expect(sections).toHaveLength(2)
		expect(sections[0].attributes('data-gateway-id')).toBe('signal')
		expect(sections[1].attributes('data-gateway-id')).toBe('telegram')
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
				{ id: 'signal', name: 'Signal', instructions: '', allowMarkdown: false, fields: [], instances: [] },
			])

		const wrapper = mount(AdminSettings)
		await flushPromises()

		// Error state shown
		expect(wrapper.find('.nc-empty-content').exists()).toBe(true)

		// Click retry
		await wrapper.find('button').trigger('click')
		await flushPromises()

		// Now gateway section renders
		expect(wrapper.find('.nc-empty-content').exists()).toBe(false)
		expect(wrapper.findAll('.gateway-section')).toHaveLength(1)
	})

	it('reloads the gateway list when a GatewaySection emits "updated"', async () => {
		const { listGateways } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(listGateways)
			.mockResolvedValueOnce([
				{ id: 'signal', name: 'Signal', instructions: '', allowMarkdown: false, fields: [], instances: [] },
			])
			.mockResolvedValueOnce([
				{ id: 'signal', name: 'Signal', instructions: '', allowMarkdown: false, fields: [], instances: [] },
				{ id: 'telegram', name: 'Telegram', instructions: '', allowMarkdown: false, fields: [], instances: [] },
			])

		const wrapper = mount(AdminSettings)
		await flushPromises()
		expect(wrapper.findAll('.gateway-section')).toHaveLength(1)

		await wrapper.findComponent({ name: 'GatewaySection' }).vm.$emit('updated')
		await flushPromises()
		expect(wrapper.findAll('.gateway-section')).toHaveLength(2)
	})
})
