// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import GatewayInstanceModal from '../../components/GatewayInstanceModal.vue'
import type { GatewayInfo } from '../../services/adminGatewayApi.ts'

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

vi.mock('dompurify', () => ({
	default: {
		sanitize: (html: string) => html,
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

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: defineComponent({
		props: ['modelValue', 'label', 'placeholder', 'required', 'error', 'helperText'],
		emits: ['update:modelValue'],
		template: '<input :value="modelValue" :placeholder="placeholder" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	}),
}))

vi.mock('@nextcloud/vue/components/NcSelect', () => ({
	default: defineComponent({
		props: ['modelValue', 'options', 'placeholder', 'label', 'trackBy', 'reduce'],
		emits: ['update:modelValue'],
		template: '<select @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option></select>',
	}),
}))

const signalGateway: GatewayInfo = {
	id: 'signal',
	name: 'Signal',
	instructions: 'Configure the Signal gateway here.',
	allowMarkdown: false,
	fields: [
		{ field: 'url', prompt: 'Gateway URL', default: 'http://localhost:5000', optional: false },
		{ field: 'account', prompt: 'Account', default: '', optional: true },
	],
	instances: [],
}

const defaultProps = {
	show: true,
	gateways: [signalGateway],
	gatewayId: '',
	instanceId: '',
	initialLabel: '',
	initialConfig: {} as Record<string, string>,
}

describe('GatewayInstanceModal (create mode)', () => {
	it('renders the modal when show is true', () => {
		const wrapper = mount(GatewayInstanceModal, { props: defaultProps })
		expect(wrapper.find('.nc-modal').exists()).toBe(true)
	})

	it('does not render when show is false', () => {
		const wrapper = mount(GatewayInstanceModal, { props: { ...defaultProps, show: false } })
		expect(wrapper.find('.nc-modal').exists()).toBe(false)
	})

	it('shows gateway selector in create mode (no instanceId)', () => {
		const wrapper = mount(GatewayInstanceModal, { props: defaultProps })
		expect(wrapper.find('select').exists()).toBe(true)
	})

	it('does not show gateway selector in edit mode (instanceId set)', () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				...defaultProps,
				gatewayId: 'signal',
				instanceId: 'abc123',
				initialLabel: 'Production',
				initialConfig: { url: 'http://signal.example.com' },
			},
		})
		expect(wrapper.find('select').exists()).toBe(false)
	})

	it('shows gateway-specific fields after selecting a gateway', async () => {
		const wrapper = mount(GatewayInstanceModal, { props: defaultProps })
		// Before gateway selection the label and config inputs are not visible
		const inputsBefore = wrapper.findAll('input').length

		// Select Signal gateway
		await wrapper.find('select').setValue('signal')
		await flushPromises()

		// Label + URL + Account fields should appear
		expect(wrapper.findAll('input').length).toBeGreaterThan(inputsBefore)
	})

	it('emits "close" when the Cancel button is clicked', async () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				...defaultProps,
				gatewayId: 'signal',
				instanceId: 'abc123',
				initialLabel: 'Production',
				initialConfig: {},
			},
		})
		const cancelButton = wrapper.findAll('button').at(0)
		await cancelButton?.trigger('click')
		expect(wrapper.emitted('close')).toBeDefined()
	})

	it('shows a disabled Save button when label is empty', () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				...defaultProps,
				gatewayId: 'signal',
				instanceId: 'abc123',
				initialLabel: '',
				initialConfig: {},
			},
		})
		const saveButton = wrapper.findAll('button').at(-1)
		expect(saveButton?.attributes('disabled')).not.toBeUndefined()
	})
})

describe('GatewayInstanceModal (edit mode)', () => {
	const editProps = {
		show: true,
		gateways: [signalGateway],
		gatewayId: 'signal',
		instanceId: 'abc123',
		initialLabel: 'Production',
		initialConfig: { url: 'http://signal.example.com', account: '+1111' } as Record<string, string>,
	}

	it('pre-fills label from initialLabel', () => {
		const wrapper = mount(GatewayInstanceModal, { props: editProps })
		const inputs = wrapper.findAll('input')
		const labelInput = inputs.find((i) => (i.element as HTMLInputElement).value === 'Production')
		expect(labelInput).toBeDefined()
	})

	it('pre-fills config fields from initialConfig', () => {
		const wrapper = mount(GatewayInstanceModal, { props: editProps })
		const inputs = wrapper.findAll('input')
		const urlInput = inputs.find((i) => (i.element as HTMLInputElement).value === 'http://signal.example.com')
		expect(urlInput).toBeDefined()
	})
})
