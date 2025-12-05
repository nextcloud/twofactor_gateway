/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
	plugins: [vue()],
	build: {
		lib: {
			entry: path.resolve(__dirname, 'src/main.ts'),
			name: 'TwoFactorGateway',
			formats: ['umd'],
			fileName: (format) => `twofactor-gateway.${format}.js`,
		},
		rollupOptions: {
			external: [
				/^vite-plugin-node-polyfills/,
				'@nextcloud/vue',
				'@nextcloud/router',
				'@nextcloud/axios',
				'@nextcloud/dialogs',
				'@nextcloud/l10n',
				'vue',
			],
			output: {
				globals: {
					vue: 'Vue',
					'@nextcloud/vue': 'NextcloudVue',
					'@nextcloud/router': 'NextcloudRouter',
					'@nextcloud/axios': 'NextcloudAxios',
					'@nextcloud/dialogs': 'NextcloudDialogs',
					'@nextcloud/l10n': 'NextcloudL10n',
				},
			},
		},
	},
})
