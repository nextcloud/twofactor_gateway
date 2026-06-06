// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { mergeConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default mergeConfig({
	resolve: {
		alias: {
			'@lib/twofactor-gateway': fileURLToPath(new URL('./src/lib/twofactor-gateway', import.meta.url)),
		},
	},
	plugins: [vue()],
}, {
	test: {
		include: ['src/tests/**/*.{test,spec}.ts'],
		environment: 'jsdom',
	},
})
