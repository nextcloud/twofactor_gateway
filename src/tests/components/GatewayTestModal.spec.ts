// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { createGatewayAdminApi, gatewayAdminApiKey, type GatewayAdminApi } from '@lib/twofactor-gateway'
import { GatewayTestModal } from '@lib/twofactor-gateway/components/gatewayTestModal'

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
		template: '<input v-bind="$attrs" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	}),
}))

const defaultProps = {
	show: true,
	gatewayId: 'signal',
	instanceId: 'abc123',
	label: 'Production',
}

function createGatewayAdminApiMock(overrides: Partial<GatewayAdminApi> = {}): GatewayAdminApi {
	return createGatewayAdminApi({
		testInstance: vi.fn(),
		...overrides,
	})
}

function mountGatewayTestModal(api: GatewayAdminApi, props = defaultProps) {
	return mount(GatewayTestModal, {
		props,
		global: {
			provide: {
				[gatewayAdminApiKey as symbol]: api,
			},
		},
	})
}

describe('GatewayTestModal', () => {
	beforeEach(() => {
		vi.resetAllMocks()
	})

	const findSendButton = (wrapper: ReturnType<typeof mount>) => {
		return wrapper.findAll('button').find((button) => button.text().includes('Send'))
	}

	it('renders when show is true', () => {
		const wrapper = mountGatewayTestModal(createGatewayAdminApiMock())
		expect(wrapper.find('.nc-modal').exists()).toBe(true)
	})

	it('does not render when show is false', () => {
		const wrapper = mountGatewayTestModal(createGatewayAdminApiMock(), { ...defaultProps, show: false })
		expect(wrapper.find('.nc-modal').exists()).toBe(false)
	})

	it('shows description mentioning the instance label', () => {
		const wrapper = mountGatewayTestModal(createGatewayAdminApiMock())
		expect(wrapper.text()).toContain('Production')
	})

	it('disables the Send Test button when identifier is empty', () => {
		const wrapper = mountGatewayTestModal(createGatewayAdminApiMock())
		// No text entered → disabled
		const sendButton = findSendButton(wrapper)
		expect(sendButton?.attributes('disabled')).not.toBeUndefined()
	})

	it('enables the Send Test button when identifier is filled', async () => {
		const wrapper = mountGatewayTestModal(createGatewayAdminApiMock())
		await wrapper.find('input').setValue('+1234567890')
		const sendButton = findSendButton(wrapper)
		expect(sendButton?.attributes('disabled')).toBeUndefined()
	})

	it('calls testInstance with the correct arguments on send', async () => {
		const api = createGatewayAdminApiMock()
		vi.mocked(api.testInstance).mockResolvedValueOnce({ success: true, message: 'Message sent successfully.' })

		const wrapper = mountGatewayTestModal(api)
		await wrapper.find('input').setValue('+1234567890')
		await findSendButton(wrapper)?.trigger('click')
		await flushPromises()

		expect(api.testInstance).toHaveBeenCalledWith('signal', 'abc123', '+1234567890')
	})

	it('sends test when pressing Enter in identifier field', async () => {
		const api = createGatewayAdminApiMock()
		vi.mocked(api.testInstance).mockResolvedValueOnce({ success: true, message: 'Message sent successfully.' })

		const wrapper = mountGatewayTestModal(api)
		await wrapper.find('input').setValue('+1234567890')
		await wrapper.find('input').trigger('keydown.enter')
		await flushPromises()

		expect(api.testInstance).toHaveBeenCalledWith('signal', 'abc123', '+1234567890')
	})

	it('does not close modal when pressing Enter in identifier field', async () => {
		const api = createGatewayAdminApiMock()
		vi.mocked(api.testInstance).mockResolvedValueOnce({ success: true, message: 'Message sent successfully.' })

		const wrapper = mountGatewayTestModal(api)
		await wrapper.find('input').setValue('+1234567890')
		await wrapper.find('input').trigger('keydown.enter')
		await flushPromises()

		expect(api.testInstance).toHaveBeenCalledTimes(1)
		expect(wrapper.emitted('close')).toBeUndefined()
	})

	it('trims identifier before sending test request', async () => {
		const api = createGatewayAdminApiMock()
		vi.mocked(api.testInstance).mockResolvedValueOnce({ success: true, message: 'Message sent successfully.' })

		const wrapper = mountGatewayTestModal(api, {
				...defaultProps,
				gatewayId: 'telegram',
		})
		await wrapper.find('input').setValue('  vitormattos  ')
		await findSendButton(wrapper)?.trigger('click')
		await flushPromises()

		expect(api.testInstance).toHaveBeenCalledWith('telegram', 'abc123', 'vitormattos')
		expect(wrapper.find('input').element).toHaveProperty('value', 'vitormattos')
	})

	it('shows a success result after a successful test', async () => {
		const api = createGatewayAdminApiMock()
		vi.mocked(api.testInstance).mockResolvedValueOnce({ success: true, message: 'Message sent successfully.' })

		const wrapper = mountGatewayTestModal(api)
		await wrapper.find('input').setValue('+1234567890')
		await findSendButton(wrapper)?.trigger('click')
		await flushPromises()

		expect(wrapper.find('.nc-note-card[data-type="success"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('Message sent successfully.')
	})

	it('shows an error result when the test fails', async () => {
		const api = createGatewayAdminApiMock()
		vi.mocked(api.testInstance).mockResolvedValueOnce({ success: false, message: 'Connection refused.' })

		const wrapper = mountGatewayTestModal(api)
		await wrapper.find('input').setValue('+1234567890')
		await findSendButton(wrapper)?.trigger('click')
		await flushPromises()

		expect(wrapper.find('.nc-note-card[data-type="error"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('Connection refused.')
	})

	it('emits "close" when the Close button is clicked', async () => {
		const wrapper = mountGatewayTestModal(createGatewayAdminApiMock())
		await wrapper.findAll('button').at(0)?.trigger('click')
		expect(wrapper.emitted('close')).toBeDefined()
	})

	it('shows initials fallback when accountInfo includes a remote avatar URL', async () => {
		const api = createGatewayAdminApiMock()
		vi.mocked(api.testInstance).mockResolvedValueOnce({
			success: true,
			message: 'Message sent successfully.',
			accountInfo: { account_name: 'Acme Corp', account_avatar_url: 'https://wa.example/avatar.png' },
		})

		const wrapper = mountGatewayTestModal(api)
		await wrapper.find('input').setValue('+1234567890')
		await findSendButton(wrapper)?.trigger('click')
		await flushPromises()

		expect(wrapper.find('.test-account-avatar').exists()).toBe(false)
		expect(wrapper.find('.test-account-avatar-fallback').text()).toBe('AC')
		expect(wrapper.find('.test-account-name').text()).toBe('Acme Corp')
	})

	it('shows account info with avatar image when accountInfo includes valid data URI', async () => {
		const api = createGatewayAdminApiMock()
		vi.mocked(api.testInstance).mockResolvedValueOnce({
			success: true,
			message: 'Message sent successfully.',
			accountInfo: {
				account_name: 'Acme Corp',
				account_avatar_url: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+n3ioAAAAASUVORK5CYII=',
			},
		})

		const wrapper = mountGatewayTestModal(api)
		await wrapper.find('input').setValue('+1234567890')
		await findSendButton(wrapper)?.trigger('click')
		await flushPromises()

		const avatar = wrapper.find('.test-account-avatar')
		expect(avatar.exists()).toBe(true)
		expect(avatar.attributes('src')).toContain('data:image/png;base64,')
		expect(avatar.attributes('alt')).toBe('Acme Corp')
		expect(wrapper.find('.test-account-name').text()).toBe('Acme Corp')
	})

	it('shows initials fallback when accountInfo has no avatar URL', async () => {
		const api = createGatewayAdminApiMock()
		vi.mocked(api.testInstance).mockResolvedValueOnce({
			success: true,
			message: 'Message sent successfully.',
			accountInfo: { account_name: 'Acme Corp', account_avatar_url: '' },
		})

		const wrapper = mountGatewayTestModal(api)
		await wrapper.find('input').setValue('+1234567890')
		await findSendButton(wrapper)?.trigger('click')
		await flushPromises()

		expect(wrapper.find('.test-account-avatar').exists()).toBe(false)
		expect(wrapper.find('.test-account-avatar-fallback').text()).toBe('AC')
		expect(wrapper.find('.test-account-name').text()).toBe('Acme Corp')
	})

	it('shows initials fallback when accountInfo avatar data URI is truncated', async () => {
		const api = createGatewayAdminApiMock()
		vi.mocked(api.testInstance).mockResolvedValueOnce({
			success: true,
			message: 'Message sent successfully.',
			accountInfo: { account_name: 'Acme Corp', account_avatar_url: 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ' },
		})

		const wrapper = mountGatewayTestModal(api)
		await wrapper.find('input').setValue('+1234567890')
		await findSendButton(wrapper)?.trigger('click')
		await flushPromises()

		expect(wrapper.find('.test-account-avatar').exists()).toBe(false)
		expect(wrapper.find('.test-account-avatar-fallback').text()).toBe('AC')
		expect(wrapper.find('.test-account-name').text()).toBe('Acme Corp')
	})

	it('does not show account info section when no accountInfo is returned', async () => {
		const api = createGatewayAdminApiMock()
		vi.mocked(api.testInstance).mockResolvedValueOnce({ success: true, message: 'Message sent successfully.' })

		const wrapper = mountGatewayTestModal(api)
		await wrapper.find('input').setValue('+1234567890')
		await findSendButton(wrapper)?.trigger('click')
		await flushPromises()

		expect(wrapper.find('.test-account-info').exists()).toBe(false)
	})
})
