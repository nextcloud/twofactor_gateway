/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
module.exports = {
	ignorePatterns: [
		'src/types/openapi/openapi.ts',
	],
	globals: {
		appName: true,
	},
	extends: [
		'@nextcloud',
		'@nextcloud/eslint-config/typescript',
	],
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'jsdoc/tag-lines': 'off',
		'vue/first-attribute-linebreak': 'off',
		'vue/max-attributes-per-line': 'off',
	},
	overrides: [
		{
			files: ['src/tests/**/*.spec.ts'],
			rules: {
				'n/no-unpublished-import': ['error', {
					allowModules: ['vitest', '@vue/test-utils', '@testing-library/vue'],
				}],
			},
		},
	],
}
