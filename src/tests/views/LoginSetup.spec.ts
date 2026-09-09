// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import LoginSetup from '../../views/LoginSetup.vue'

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

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (url: string, params: Record<string, string> = {}) => Object.entries(params).reduce(
		(acc, [key, value]) => acc.replace(`{${key}}`, value),
		url,
	),
}))

vi.mock('dompurify', () => ({
	default: { sanitize: (value: string) => value },
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: defineComponent({
		emits: ['click'],
		template: '<button type="button" @click="$emit(\'click\', $event)"><slot /></button>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
	default: defineComponent({ template: '<div class="nc-loading-icon" />' }),
}))

vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: defineComponent({
		props: ['modelValue', 'error', 'helperText'],
		emits: ['update:modelValue'],
		template: '<input type="text" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
	}),
}))

const makeProps = (overrides: Record<string, unknown> = {}) => ({
	gatewayName: 'signal',
	displayName: 'Signal',
	instructions: 'Install Signal first',
	isComplete: true,
	...overrides,
})

describe('LoginSetup', () => {
	it('shows the not-available message when the gateway is not configured', async () => {
		const wrapper = mount(LoginSetup, { props: makeProps({ isComplete: false }) })
		await flushPromises()

		expect(wrapper.text()).toContain('tr:Signal is not available. Please ask your administrator to finish setting it up.')
		expect(wrapper.find('.nc-loading-icon').exists()).toBe(false)
	})

	it('starts at the identifier step when the user has no in-flight verification', async () => {
		const axios = (await import('@nextcloud/axios')).default
		vi.mocked(axios.get).mockResolvedValueOnce({ data: { state: 0, phoneNumber: null } })

		const wrapper = mount(LoginSetup, { props: makeProps() })
		await flushPromises()

		expect((wrapper.vm as unknown as { state: number }).state).toBe(1)
		expect(wrapper.text()).toContain('tr:Enter your identification (e.g. phone number to start the verification):')
	})

	it('resumes at the confirmation step when the server reports state 2', async () => {
		const axios = (await import('@nextcloud/axios')).default
		vi.mocked(axios.get).mockResolvedValueOnce({ data: { state: 2, phoneNumber: '+33 6 ** ** ** 12' } })

		const wrapper = mount(LoginSetup, { props: makeProps() })
		await flushPromises()

		expect((wrapper.vm as unknown as { state: number }).state).toBe(2)
		expect(wrapper.text()).toContain('+33 6 ** ** ** 12')
	})

	it('moves to the confirmation step after a successful verify call', async () => {
		const axios = (await import('@nextcloud/axios')).default
		vi.mocked(axios.get).mockResolvedValueOnce({ data: { state: 0, phoneNumber: null } })
		vi.mocked(axios.post).mockResolvedValueOnce({ data: { phoneNumber: '+33 6 ** ** ** 12' } })

		const wrapper = mount(LoginSetup, { props: makeProps() })
		await flushPromises()

		const vm = wrapper.vm as unknown as { identifier: string; verify: () => Promise<void>; state: number; phoneNumber: string }
		vm.identifier = '+33612345612'
		await vm.verify()
		await flushPromises()

		expect(vm.state).toBe(2)
		expect(vm.phoneNumber).toBe('+33 6 ** ** ** 12')
	})

	it('surfaces a verification error when the confirm call fails', async () => {
		const axios = (await import('@nextcloud/axios')).default
		vi.mocked(axios.get).mockResolvedValueOnce({ data: { state: 2, phoneNumber: '+33' } })
		vi.mocked(axios.post).mockRejectedValueOnce({ response: { data: { ocs: { data: { message: 'Wrong code' } } } } })

		const wrapper = mount(LoginSetup, { props: makeProps() })
		await flushPromises()

		const vm = wrapper.vm as unknown as { confirmationCode: string; confirm: () => Promise<void>; state: number; verificationError: string }
		vm.confirmationCode = '000000'
		await vm.confirm()
		await flushPromises()

		expect(vm.state).toBe(1)
		expect(vm.verificationError).toBe('Wrong code')
	})

	it('submits the redirect form after a successful confirmation', async () => {
		const axios = (await import('@nextcloud/axios')).default
		vi.mocked(axios.get).mockResolvedValueOnce({ data: { state: 2, phoneNumber: '+33' } })
		vi.mocked(axios.post).mockResolvedValueOnce({ data: {} })

		const wrapper = mount(LoginSetup, { props: makeProps() })
		await flushPromises()

		const submit = vi.fn()
		const vm = wrapper.vm as unknown as {
			confirmationCode: string
			confirm: () => Promise<void>
			state: number
			$refs: { redirectForm: HTMLFormElement }
		}
		vm.$refs.redirectForm.submit = submit
		vm.confirmationCode = '123456'
		await vm.confirm()
		await flushPromises()

		expect(vm.state).toBe(3)
		expect(submit).toHaveBeenCalledTimes(1)
	})
})
