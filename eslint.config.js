/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		name: 'twofactor_gateway/ignores',
		ignores: [
			'src/types/openapi/openapi.ts',
		],
	},
	{
		name: 'twofactor_gateway/config',
		languageOptions: {
			globals: {
				appName: 'writable',
			},
		},
		rules: {
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/tag-lines': 'off',
			'vue/first-attribute-linebreak': 'off',
			'vue/max-attributes-per-line': 'off',
		},
	},
	{
		name: 'twofactor_gateway/tests',
		files: ['src/tests/**/*.spec.ts'],
		rules: {
			'n/no-unpublished-import': ['error', {
				allowModules: ['vitest', '@vue/test-utils', '@testing-library/vue'],
			}],
		},
	},
]
