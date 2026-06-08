// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { createGatewayAdminApi, gatewayAdminApiKey } from '@lib/twofactor-gateway'
import SetupPanel from '../../../../components/providers/whatsappbusiness/SetupPanel.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string, vars?: Record<string, string>) => text.replace(/\{(\w+)\}/g, (_match, key: string) => vars?.[key] ?? `{${key}}`),
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: defineComponent({
		props: ['disabled'],
		emits: ['click'],
		template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
	default: defineComponent({ template: '<span class="nc-loading-icon" />' }),
}))

vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({
	default: defineComponent({ props: ['type'], template: '<div class="nc-note-card" :data-type="type"><slot /></div>' }),
}))

vi.mock('@nextcloud/vue/components/NcPasswordField', () => ({
	default: defineComponent({
		props: ['modelValue', 'label', 'required', 'placeholder'],
		emits: ['update:modelValue'],
		template: '<label class="nc-password-field"><span class="field-label">{{ label }}</span><input type="password" :value="modelValue" :placeholder="placeholder" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: defineComponent({
		props: ['modelValue', 'label', 'required', 'placeholder'],
		emits: ['update:modelValue'],
		template: '<label class="nc-text-field"><span class="field-label">{{ label }}</span><input type="text" :value="modelValue" :placeholder="placeholder" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
	}),
}))

describe('WhatsApp Business SetupPanel', () => {
	it('uses a parameterized manual template placeholder', async () => {
		const gatewayAdminApi = createGatewayAdminApi({
			startInteractiveSetup: vi.fn(),
			interactiveSetupStep: vi.fn(),
			cancelInteractiveSetup: vi.fn(),
		})

		const wrapper = mount(SetupPanel, {
			props: {
				gatewayId: 'whatsapp',
				providerId: 'whatsappbusiness',
				config: {},
				canStart: true,
			},
			global: {
				provide: {
					[gatewayAdminApiKey as symbol]: gatewayAdminApi,
				},
			},
		})

		expect((wrapper.vm as unknown as { manualTemplateNamePlaceholder: string }).manualTemplateNamePlaceholder).toBe('e.g. verification_code_v1')
	})
})
