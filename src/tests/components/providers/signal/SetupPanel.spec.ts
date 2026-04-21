// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import SetupPanel from '../../../../components/providers/signal/SetupPanel.vue'

const { sanitizeMock } = vi.hoisted(() => ({
	sanitizeMock: vi.fn((html: string) => html),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
}))

vi.mock('dompurify', () => ({
	default: {
		sanitize: sanitizeMock,
	},
}))

vi.mock('../../../../services/adminGatewayApi.ts', () => ({
	startInteractiveSetup: vi.fn(),
	interactiveSetupStep: vi.fn(),
	cancelInteractiveSetup: vi.fn(),
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

vi.mock('@nextcloud/vue/components/NcProgressBar', () => ({
	default: defineComponent({ props: ['value'], template: '<div class="nc-progress" :data-value="value" />' }),
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: defineComponent({
		props: ['modelValue', 'label', 'required', 'placeholder'],
		emits: ['update:modelValue'],
		template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	}),
}))

describe('Signal SetupPanel', () => {
	it('sanitizes QR SVG before rendering with v-html', async () => {
		sanitizeMock.mockReturnValue('<svg><rect /></svg>')

		const wrapper = mount(SetupPanel, {
			props: {
				gatewayId: 'signal',
				providerId: 'signal',
				config: {},
				canStart: true,
			},
		})

		await wrapper.setData({
			wizardStep: 'scan_qr',
			wizardQrSvg: '<svg><script>alert(1)</script><rect /></svg>',
		})

		expect(sanitizeMock).toHaveBeenCalledWith(
			'<svg><script>alert(1)</script><rect /></svg>',
			expect.objectContaining({
				USE_PROFILES: { svg: true, svgFilters: true },
				FORBID_TAGS: ['script', 'foreignObject'],
				FORBID_ATTR: ['onload', 'onclick', 'onerror'],
			}),
		)
		const qrHtml = wrapper.find('.wizard-qr-wrapper').html()
		expect(qrHtml).toContain('<svg')
		expect(qrHtml).toContain('<rect')
		expect(qrHtml).not.toContain('<script>')
	})
})