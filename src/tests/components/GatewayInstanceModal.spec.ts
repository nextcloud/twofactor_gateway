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
		template: '<input type="text" :value="modelValue" :placeholder="placeholder" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	}),
}))

vi.mock('@nextcloud/vue/components/NcPasswordField', () => ({
	default: defineComponent({
		props: ['modelValue', 'label', 'placeholder', 'required', 'error', 'helperText'],
		emits: ['update:modelValue'],
		template: '<input type="password" :value="modelValue" :placeholder="placeholder" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	}),
}))

vi.mock('@nextcloud/vue/components/NcSelect', () => ({
	default: defineComponent({
		props: ['modelValue', 'options', 'placeholder', 'label', 'trackBy', 'reduce'],
		emits: ['update:modelValue'],
		template: '<select @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option></select>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
	default: defineComponent({
		props: ['modelValue', 'type'],
		emits: ['update:modelValue'],
		template: '<label class="nc-checkbox-radio-switch"><input type="checkbox" :checked="!!modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
	}),
}))

vi.mock('../../components/providers/registry', () => ({
	resolveGatewaySetupPanel: (providerId: string) => {
		if (providerId === 'gowhatsapp') {
			return defineComponent({
				name: 'MockGatewaySetupPanel',
				props: ['providerId', 'config', 'canStart'],
				emits: ['merge-config', 'setup-completed', 'update:wizardActive'],
				template: '<div class="mock-gateway-setup-panel" />',
			})
		}

		return null
	},
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

const smsLikeGatewayWithCatalog: GatewayInfo = {
	id: 'sms',
	name: 'SMS',
	instructions: '',
	allowMarkdown: false,
	fields: [],
	providerSelector: { field: 'provider', prompt: 'SMS channel/provider', default: '', optional: false },
	providerCatalog: [
		{
			id: 'smsapi',
			name: 'SMSApi',
			fields: [
				{ field: 'token', prompt: 'Token', default: '', optional: false },
				{ field: 'sender', prompt: 'Sender', default: '', optional: true },
			],
		},
	],
	instances: [],
}

const goWhatsAppGateway: GatewayInfo = {
	id: 'gowhatsapp',
	name: 'WhatsApp',
	instructions: '',
	allowMarkdown: false,
	fields: [
		{ field: 'base_url', prompt: 'Base URL to your WhatsApp API endpoint:', default: '', optional: false },
		{ field: 'phone', prompt: 'Phone number for WhatsApp Web access:', default: '', optional: false },
		{ field: 'device_name', prompt: 'Device name shown in WhatsApp linked devices:', default: 'TwoFactor Gateway', optional: true },
		{ field: 'username', prompt: 'API Username:', default: '', optional: true },
		{ field: 'password', prompt: 'API Password:', default: '', optional: true, type: 'secret' },
		{ field: 'webhook_hybrid_enabled', prompt: 'Enable hybrid monitoring webhook:', default: '0', optional: true, type: 'boolean' },
		{ field: 'webhook_secret', prompt: 'Webhook HMAC secret for X-Hub-Signature-256:', default: '', optional: true, type: 'secret' },
		{ field: 'webhook_min_check_interval', prompt: 'Minimum seconds between webhook-triggered checks:', default: '30', optional: true, type: 'integer' },
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

	it('shows provider/channel options first for catalog gateways, then renders provider fields', async () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [smsLikeGatewayWithCatalog],
				gatewayId: '',
				instanceId: '',
				initialLabel: '',
				initialConfig: {},
			},
		})

		// Select SMS gateway first
		await wrapper.find('select').setValue('sms')
		await flushPromises()

		// Single-provider catalogs should auto-select the only provider.
		const selects = wrapper.findAll('select')
		expect(selects).toHaveLength(1)
		await flushPromises()

		// Label + provider fields are visible without an extra provider dropdown.
		expect(wrapper.findAll('input').length).toBeGreaterThan(0)
	})

	it('uses wizard-first flow for GoWhatsApp: shows only label form field and wizard panel with no Save', async () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [goWhatsAppGateway],
				gatewayId: '',
				instanceId: '',
				initialLabel: '',
				initialConfig: {},
			},
		})

		await wrapper.find('select').setValue('gowhatsapp')
		await flushPromises()

		// Wizard panel is rendered
		expect(wrapper.find('.mock-gateway-setup-panel').exists()).toBe(true)

		// No config inputs rendered directly in the modal (they live inside the wizard panel)
		const inputPlaceholders = wrapper.findAll('input').map(
			(i) => (i.element as HTMLInputElement).placeholder,
		)
		expect(inputPlaceholders).not.toContain('http://whatsapp.web:3000')

		// No advanced section toggle rendered
		const advancedToggle = wrapper.findAll('button').find((b) => b.text().includes('Show advanced fields'))
		expect(advancedToggle).toBeUndefined()

		// In wizard-first create flow, Save is hidden to avoid conflict with guided setup action.
		const saveButton = wrapper.findAll('button').find((button) => button.text().includes('tr:Save'))
		expect(saveButton).toBeUndefined()
	})

	it('fieldsToValidate is empty in wizard-first create mode', async () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [goWhatsAppGateway],
				gatewayId: '',
				instanceId: '',
				initialLabel: '',
				initialConfig: {},
			},
		})

		await wrapper.find('select').setValue('gowhatsapp')
		await flushPromises()

		const fields = (wrapper.vm as unknown as { fieldsToValidate: Array<{ field: string }> }).fieldsToValidate
		expect(fields).toHaveLength(0)
	})

	it('hides Provider selector and Label when wizard session becomes active', async () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [goWhatsAppGateway],
				gatewayId: '',
				instanceId: '',
				initialLabel: 'DevInspiron',
				initialConfig: {},
			},
		})

		await wrapper.find('select').setValue('gowhatsapp')
		await flushPromises()

		// Before session: select (provider) and label input are present
		expect(wrapper.find('select').exists()).toBe(true)
		const labelInputBefore = wrapper.findAll('input').find(
			(i) => (i.element as HTMLInputElement).value === 'DevInspiron',
		)
		expect(labelInputBefore).toBeDefined()

		// Simulate wizard session starting
		const setupPanel = wrapper.findComponent({ name: 'MockGatewaySetupPanel' })
		setupPanel.vm.$emit('update:wizardActive', true)
		await flushPromises()

		// After session starts: provider select and label input are hidden
		expect(wrapper.find('select').exists()).toBe(false)
		const labelInputAfter = wrapper.findAll('input').find(
			(i) => (i.element as HTMLInputElement).value === 'DevInspiron',
		)
		expect(labelInputAfter).toBeUndefined()
	})

	it('restores Provider selector and Label when wizard session is cancelled (wizardActive = false)', async () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [goWhatsAppGateway],
				gatewayId: '',
				instanceId: '',
				initialLabel: 'DevInspiron',
				initialConfig: {},
			},
		})

		await wrapper.find('select').setValue('gowhatsapp')
		await flushPromises()

		const setupPanel = wrapper.findComponent({ name: 'MockGatewaySetupPanel' })

		// Activate then deactivate
		setupPanel.vm.$emit('update:wizardActive', true)
		await flushPromises()
		setupPanel.vm.$emit('update:wizardActive', false)
		await flushPromises()

		expect(wrapper.find('select').exists()).toBe(true)
		const labelInput = wrapper.findAll('input').find(
			(i) => (i.element as HTMLInputElement).value === 'DevInspiron',
		)
		expect(labelInput).toBeDefined()
	})

	it('passes canStart=false to guided setup when label is empty', async () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [goWhatsAppGateway],
				gatewayId: '',
				instanceId: '',
				initialLabel: '',
				initialConfig: {},
			},
		})

		await wrapper.find('select').setValue('gowhatsapp')
		await flushPromises()

		const setupPanel = wrapper.findComponent({ name: 'MockGatewaySetupPanel' })
		expect(setupPanel.exists()).toBe(true)
		expect(setupPanel.props('canStart')).toBe(false)
	})

	it('auto-saves when guided setup emits setup-completed', async () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [goWhatsAppGateway],
				gatewayId: '',
				instanceId: '',
				initialLabel: '',
				initialConfig: {},
			},
		})

		await wrapper.find('select').setValue('gowhatsapp')
		await flushPromises()

		// First text input in create mode is label.
		const labelInput = wrapper.find('input[type="text"]')
		await labelInput.setValue('Primary WhatsApp')
		await flushPromises()

		const setupPanel = wrapper.findComponent({ name: 'MockGatewaySetupPanel' })
		setupPanel.vm.$emit('setup-completed', {
			base_url: 'https://wa.example.com',
			device_name: 'TwoFactor Gateway',
		})
		await flushPromises()

		const savedPayload = wrapper.emitted('saved')?.[0]?.[0] as { gatewayId: string; label: string; config: Record<string, string> } | undefined
		expect(savedPayload).toBeDefined()
		expect(savedPayload?.gatewayId).toBe('gowhatsapp')
		expect(savedPayload?.label).toBe('Primary WhatsApp')
		expect(savedPayload?.config.base_url).toBe('https://wa.example.com')
	})

	it('hides provider selector when catalog has duplicate entries for the same provider id', async () => {
		const duplicateCatalogGateway: GatewayInfo = {
			...smsLikeGatewayWithCatalog,
			providerCatalog: [
				...(smsLikeGatewayWithCatalog.providerCatalog ?? []),
				...(smsLikeGatewayWithCatalog.providerCatalog ?? []),
			],
		}

		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [duplicateCatalogGateway],
				gatewayId: '',
				instanceId: '',
				initialLabel: '',
				initialConfig: {},
			},
		})

		await wrapper.find('select').setValue('sms')
		await flushPromises()

		// Single unique provider should auto-select and keep only the gateway selector visible.
		expect(wrapper.findAll('select')).toHaveLength(1)
		expect((wrapper.vm as unknown as { form: { config: Record<string, string> } }).form.config.provider).toBe('smsapi')
	})

	it('hides provider selector when multiple provider ids resolve to one visible label', async () => {
		const duplicateLabelGateway: GatewayInfo = {
			...smsLikeGatewayWithCatalog,
			providerCatalog: [
				{
					id: 'smsapi',
					name: 'WhatsApp',
					fields: [
						{ field: 'token', prompt: 'Token', default: '', optional: false },
					],
				},
				{
					id: 'gowhatsapp',
					name: 'WhatsApp',
					fields: [
						{ field: 'base_url', prompt: 'Base URL', default: '', optional: false },
					],
				},
			],
		}

		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [duplicateLabelGateway],
				gatewayId: '',
				instanceId: '',
				initialLabel: '',
				initialConfig: {},
			},
		})

		await wrapper.find('select').setValue('sms')
		await flushPromises()

		expect(wrapper.findAll('select')).toHaveLength(1)
		expect((wrapper.vm as unknown as { form: { config: Record<string, string> } }).form.config.provider).toBe('smsapi')
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

	it('does not show provider selector while editing catalog-based instance', () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [smsLikeGatewayWithCatalog],
				gatewayId: 'sms',
				instanceId: 'smsapi:abc123',
				initialLabel: 'Prod',
				initialConfig: { provider: 'smsapi', token: 'abc' },
			},
		})

		expect(wrapper.findAll('select')).toHaveLength(0)
	})

	it('renders catalog fields in edit mode when provider comes from instance id prefix', async () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [smsLikeGatewayWithCatalog],
				gatewayId: 'sms',
				instanceId: 'smsapi:abc123',
				initialLabel: 'Prod',
				initialConfig: { token: 'abc' },
			},
		})

		await flushPromises()
		// Label + provider fields should be rendered in edit mode.
		expect(wrapper.findAll('input').length).toBeGreaterThan(1)
	})

	it('renders edit fields even when gatewayId is empty, inferring from provider hints', async () => {
		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [smsLikeGatewayWithCatalog],
				gatewayId: '',
				instanceId: 'smsapi:abc123',
				initialLabel: 'Prod',
				initialConfig: { provider: 'smsapi', token: 'abc' },
			},
		})

		await flushPromises()
		expect(wrapper.findAll('input').length).toBeGreaterThan(1)
		expect(wrapper.findAll('select')).toHaveLength(0)
	})

	it('allows editing with empty secret fields without failing validation', async () => {
		const secretGateway: GatewayInfo = {
			id: 'secretgw',
			name: 'Secret Gateway',
			instructions: '',
			allowMarkdown: false,
			fields: [
				{ field: 'password', prompt: 'Password', default: '', optional: false, type: 'secret' },
			],
			instances: [],
		}

		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [secretGateway],
				gatewayId: 'secretgw',
				instanceId: 'abc123',
				initialLabel: 'Prod',
				initialConfig: { password: '' },
			},
		})

		const saveButton = wrapper.findAll('button').at(-1)
		await saveButton?.trigger('click')
		expect(wrapper.emitted('saved')).toBeDefined()
	})

	it('renders secret fields using password input component', () => {
		const secretGateway: GatewayInfo = {
			id: 'secretgw',
			name: 'Secret Gateway',
			instructions: '',
			allowMarkdown: false,
			fields: [
				{ field: 'password', prompt: 'Password', default: '', optional: false, type: 'secret' },
			],
			instances: [],
		}

		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [secretGateway],
				gatewayId: 'secretgw',
				instanceId: 'abc123',
				initialLabel: 'Prod',
				initialConfig: { password: '' },
			},
		})

		expect(wrapper.findAll('input[type="password"]')).toHaveLength(1)
	})

	it('does not render hidden metadata fields', async () => {
		const hiddenFieldGateway: GatewayInfo = {
			id: 'whatsapp',
			name: 'WhatsApp',
			instructions: '',
			allowMarkdown: false,
			fields: [
				{ field: 'base_url', prompt: 'Base URL', default: '', optional: false },
				{ field: 'session_id', prompt: 'Session ID', default: '__INSTANCE_ID__', optional: true, hidden: true },
			],
			instances: [],
		}

		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [hiddenFieldGateway],
				gatewayId: 'whatsapp',
				instanceId: 'abc123',
				initialLabel: 'Legacy',
				initialConfig: { base_url: 'https://wa.example.com', session_id: 'abc123' },
			},
		})

		await flushPromises()
		expect(wrapper.findAll('input')).toHaveLength(2)
	})

	it('renders boolean fields as switch and emits 0/1 values', async () => {
		const boolGateway: GatewayInfo = {
			id: 'gowhatsapp',
			name: 'WhatsApp',
			instructions: '',
			allowMarkdown: false,
			fields: [
				{ field: 'base_url', prompt: 'Base URL', default: '', optional: false },
				{ field: 'webhook_hybrid_enabled', prompt: 'Enable hybrid monitoring webhook', default: '0', optional: true, type: 'boolean' },
			],
			instances: [],
		}

		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [boolGateway],
				gatewayId: 'gowhatsapp',
				instanceId: 'abc123',
				initialLabel: 'Legacy',
				initialConfig: { base_url: 'https://wa.example.com', webhook_hybrid_enabled: '0' },
			},
		})

		await flushPromises()
		const switchInput = wrapper.find('input[type="checkbox"]')
		expect(switchInput.exists()).toBe(true)
		await switchInput.setValue(true)

		const saveButton = wrapper.findAll('button').at(-1)
		await saveButton?.trigger('click')

		const savedPayload = wrapper.emitted('saved')?.[0]?.[0] as { config: Record<string, string> } | undefined
		expect(savedPayload?.config.webhook_hybrid_enabled).toBe('1')
	})

	it('validates integer fields with min/max and saves valid value', async () => {
		const intGateway: GatewayInfo = {
			id: 'gowhatsapp',
			name: 'WhatsApp',
			instructions: '',
			allowMarkdown: false,
			fields: [
				{ field: 'base_url', prompt: 'Base URL', default: '', optional: false },
				{ field: 'webhook_min_check_interval', prompt: 'Minimum seconds', default: '30', optional: true, type: 'integer', min: 0, max: 3600 },
			],
			instances: [],
		}

		const wrapper = mount(GatewayInstanceModal, {
			props: {
				show: true,
				gateways: [intGateway],
				gatewayId: 'gowhatsapp',
				instanceId: 'abc123',
				initialLabel: 'Legacy',
				initialConfig: { base_url: 'https://wa.example.com', webhook_min_check_interval: '30' },
			},
		})

		await flushPromises()

		const textInputs = wrapper.findAll('input[type="text"]')
		const integerInput = textInputs.at(-1)
		expect(integerInput?.exists()).toBe(true)

		await integerInput?.setValue('-1')
		const saveButton = wrapper.findAll('button').at(-1)
		await saveButton?.trigger('click')
		expect(wrapper.emitted('saved')).toBeUndefined()

		await integerInput?.setValue('120')
		await saveButton?.trigger('click')

		const savedPayload = wrapper.emitted('saved')?.[0]?.[0] as { config: Record<string, string> } | undefined
		expect(savedPayload?.config.webhook_min_check_interval).toBe('120')
	})
})
