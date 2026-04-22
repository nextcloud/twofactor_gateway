// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { mergeConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default mergeConfig({
	plugins: [vue()],
}, {
	test: {
		include: ['src/tests/**/*.{test,spec}.ts'],
		environment: 'jsdom',
	},
})
