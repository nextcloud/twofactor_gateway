// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import GatewayRoutingModal from '../../components/GatewayRoutingModal.vue'

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

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: defineComponent({
		props: ['modelValue', 'label', 'placeholder', 'required', 'error', 'helperText', 'readonly'],
		emits: ['update:modelValue'],
		template: '<input type="text" :value="modelValue" :placeholder="placeholder" :readonly="readonly" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	}),
}))

vi.mock('@nextcloud/vue/components/NcSelect', () => ({
	default: defineComponent({
		props: ['modelValue', 'options', 'placeholder', 'label', 'trackBy', 'multiple', 'keepOpen', 'closeOnSelect', 'deselectFromDropdown', 'inputId'],
		emits: ['update:modelValue'],
		template: `<div class="nc-select-mock">
			<select :multiple="multiple" @change="onSelectChange">
				<option v-for="opt in options" :key="opt.id" :value="opt.id">{{ opt.displayName }}</option>
			</select>
		</div>`,
		methods: {
			onSelectChange(event: Event) {
				const select = event.target as HTMLSelectElement
				const selectedIds = Array.from(select.selectedOptions).map((option) => option.value)
				const selectedObjects = (this.options as Array<{ id: string; displayName: string }>).filter((option) => selectedIds.includes(option.id))
				this.$emit('update:modelValue', selectedObjects)
			},
		},
	}),
}))

describe('GatewayRoutingModal', () => {
	it('renders current label and reference as static metadata', async () => {
		const wrapper = mount(GatewayRoutingModal, {
			props: {
				show: true,
				label: 'WhatsApp Ops',
				instanceId: 'gw-1',
			},
		})

		await flushPromises()
		expect(wrapper.find('.routing-meta-grid').exists()).toBe(true)
		expect(wrapper.text()).toContain('WhatsApp Ops')
		expect(wrapper.text()).toContain('gw-1')
		expect(wrapper.findAll('input[type="text"]')).toHaveLength(1)
	})

	it('emits selected groups and priority on save', async () => {
		const wrapper = mount(GatewayRoutingModal, {
			props: {
				show: true,
				label: 'WhatsApp Ops',
				instanceId: 'gw-1',
				groups: [
					{ id: 'admins', displayName: 'Admins' },
					{ id: 'client-a', displayName: 'Client A' },
				],
				initialGroupIds: ['admins'],
				initialPriority: 15,
			},
		})

		await flushPromises()

		const priorityInput = wrapper.find('input[type="text"]')
		await priorityInput?.setValue('30')

		const groupSelect = wrapper.find('.nc-select-mock select')
		await groupSelect.setValue(['admins', 'client-a'])

		const saveButton = wrapper.findAll('button').at(-1)
		await saveButton?.trigger('click')

		const savedPayload = wrapper.emitted('saved')?.[0]?.[0] as { groupIds: string[]; priority: number } | undefined
		expect(savedPayload?.priority).toBe(30)
		expect(savedPayload?.groupIds).toEqual(['admins', 'client-a'])
	})

	it('shows validation error for non-integer priority', async () => {
		const wrapper = mount(GatewayRoutingModal, {
			props: {
				show: true,
				label: 'WhatsApp Ops',
				instanceId: 'gw-1',
			},
		})

		await flushPromises()

		const priorityInput = wrapper.find('input[type="text"]')
		await priorityInput?.setValue('abc')

		const saveButton = wrapper.findAll('button').at(-1)
		await saveButton?.trigger('click')

		expect(wrapper.emitted('saved')).toBeUndefined()
		expect((wrapper.vm as { errors: Record<string, string> }).errors.priority).toBe('tr:Priority must be an integer.')
	})
})
