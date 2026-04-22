/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import eslint from 'vite-plugin-eslint'
import stylelint from 'vite-plugin-stylelint'
import path from 'path'

const isProduction = process.env.NODE_ENV === 'production'

export default createAppConfig({
	main: path.join(__dirname, 'src', 'main.js'),
	admin: path.join(__dirname, 'src', 'admin.ts'),
}, {
	config: {
		plugins: [
			...(!isProduction ? [eslint()] : []),
			stylelint(),
		],
	},
	minify: isProduction,
})
