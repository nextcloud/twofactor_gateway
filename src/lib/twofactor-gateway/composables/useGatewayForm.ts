// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { computed, ref } from 'vue'

export function useGatewayForm(initialLabel = '', initialConfig: Record<string, string> = {}) {
	const label = ref(initialLabel)
	const config = ref<Record<string, string>>({ ...initialConfig })

	const canSave = computed(() => label.value.trim() !== '')

	function reset(nextLabel = '', nextConfig: Record<string, string> = {}) {
		label.value = nextLabel
		config.value = { ...nextConfig }
	}

	return {
		label,
		config,
		canSave,
		reset,
	}
}
