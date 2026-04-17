// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import GatewayTestModal from '../../components/GatewayTestModal.vue'

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
	testInstance: vi.fn(),
}))

vi.mock('@nextcloud/vue/components/NcModal', () => ({
	default: defineComponent({
		props: ['name', 'show', 'size'],
		emits: ['close'],
		template: '<div v-if="show" class="nc-modal"><slot /></div>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: defineComponent({
		props: ['type', 'disabled'],
		emits: ['click'],
		template: '<button :disabled="disabled" type="button" @click="$emit(\'click\', $event)"><slot /><slot name="icon" /></button>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
	default: defineComponent({ template: '<span class="nc-loading-icon" />' }),
}))

vi.mock('@nextcloud/vue/components/NcAvatar', () => ({
	default: defineComponent({
		props: ['displayName', 'url', 'size', 'isNoUser'],
		template: '<span class="nc-avatar" :data-display-name="displayName" :data-url="url || \'\'" />',
	}),
}))

vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({
	default: defineComponent({
		props: ['type'],
		template: '<div class="nc-note-card" :data-type="type"><slot /></div>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: defineComponent({
		props: ['modelValue', 'label', 'placeholder', 'required'],
		emits: ['update:modelValue'],
		template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	}),
}))

const defaultProps = {
	show: true,
	gatewayId: 'signal',
	instanceId: 'abc123',
	label: 'Production',
}

describe('GatewayTestModal', () => {
	it('renders when show is true', () => {
		const wrapper = mount(GatewayTestModal, { props: defaultProps })
		expect(wrapper.find('.nc-modal').exists()).toBe(true)
	})

	it('does not render when show is false', () => {
		const wrapper = mount(GatewayTestModal, { props: { ...defaultProps, show: false } })
		expect(wrapper.find('.nc-modal').exists()).toBe(false)
	})

	it('shows description mentioning the instance label', () => {
		const wrapper = mount(GatewayTestModal, { props: defaultProps })
		expect(wrapper.text()).toContain('Production')
	})

	it('disables the Send Test button when identifier is empty', () => {
		const wrapper = mount(GatewayTestModal, { props: defaultProps })
		// No text entered → disabled
		const sendButton = wrapper.findAll('button').at(-1)
		expect(sendButton?.attributes('disabled')).not.toBeUndefined()
	})

	it('enables the Send Test button when identifier is filled', async () => {
		const wrapper = mount(GatewayTestModal, { props: defaultProps })
		await wrapper.find('input').setValue('+1234567890')
		const sendButton = wrapper.findAll('button').at(-1)
		expect(sendButton?.attributes('disabled')).toBeUndefined()
	})

	it('calls testInstance with the correct arguments on send', async () => {
		const { testInstance } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(testInstance).mockResolvedValueOnce({ success: true, message: 'Message sent successfully.' })

		const wrapper = mount(GatewayTestModal, { props: defaultProps })
		await wrapper.find('input').setValue('+1234567890')
		await wrapper.findAll('button').at(-1)?.trigger('click')
		await flushPromises()

		expect(testInstance).toHaveBeenCalledWith('signal', 'abc123', '+1234567890')
	})

	it('shows a success result after a successful test', async () => {
		const { testInstance } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(testInstance).mockResolvedValueOnce({ success: true, message: 'Message sent successfully.' })

		const wrapper = mount(GatewayTestModal, { props: defaultProps })
		await wrapper.find('input').setValue('+1234567890')
		await wrapper.findAll('button').at(-1)?.trigger('click')
		await flushPromises()

		expect(wrapper.find('.nc-note-card[data-type="success"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('Message sent successfully.')
	})

	it('shows an error result when the test fails', async () => {
		const { testInstance } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(testInstance).mockResolvedValueOnce({ success: false, message: 'Connection refused.' })

		const wrapper = mount(GatewayTestModal, { props: defaultProps })
		await wrapper.find('input').setValue('+1234567890')
		await wrapper.findAll('button').at(-1)?.trigger('click')
		await flushPromises()

		expect(wrapper.find('.nc-note-card[data-type="error"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('Connection refused.')
	})

	it('emits "close" when the Close button is clicked', async () => {
		const wrapper = mount(GatewayTestModal, { props: defaultProps })
		await wrapper.findAll('button').at(0)?.trigger('click')
		expect(wrapper.emitted('close')).toBeDefined()
	})

	it('shows account info with NcAvatar when accountInfo is returned', async () => {
		const { testInstance } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(testInstance).mockResolvedValueOnce({
			success: true,
			message: 'Message sent successfully.',
			accountInfo: { account_name: 'Acme Corp', account_avatar_url: 'https://wa.example/avatar.png' },
		})

		const wrapper = mount(GatewayTestModal, { props: defaultProps })
		await wrapper.find('input').setValue('+1234567890')
		await wrapper.findAll('button').at(-1)?.trigger('click')
		await flushPromises()

		const avatar = wrapper.find('.nc-avatar')
		expect(avatar.exists()).toBe(true)
		expect(avatar.attributes('data-display-name')).toBe('Acme Corp')
		expect(avatar.attributes('data-url')).toBe('https://wa.example/avatar.png')
		expect(wrapper.find('.test-account-name').text()).toBe('Acme Corp')
	})

	it('does not show account info section when no accountInfo is returned', async () => {
		const { testInstance } = await import('../../services/adminGatewayApi.ts')
		vi.mocked(testInstance).mockResolvedValueOnce({ success: true, message: 'Message sent successfully.' })

		const wrapper = mount(GatewayTestModal, { props: defaultProps })
		await wrapper.find('input').setValue('+1234567890')
		await wrapper.findAll('button').at(-1)?.trigger('click')
		await flushPromises()

		expect(wrapper.find('.test-account-info').exists()).toBe(false)
	})
})
