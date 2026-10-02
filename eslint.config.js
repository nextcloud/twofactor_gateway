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
			'doc/assets/openapi-explorer.js',
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
			// Existing project choices from the legacy configuration.
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/tag-lines': 'off',
			'no-console': ['error', { allow: ['error', 'warn', 'info', 'debug'] }],
			'vue/first-attribute-linebreak': 'off',
			'vue/max-attributes-per-line': 'off',

			// TODO: Migrate these new v9 style rules in a dedicated cleanup.
			'import-extensions/extensions': 'off',
			'import-extensions/ban-inline-type-imports': 'off',
			'perfectionist/sort-imports': 'off',
			'perfectionist/sort-named-imports': 'off',
			'antfu/top-level-function': 'off',
			'@stylistic/exp-list-style': 'off',
			'@stylistic/function-call-argument-newline': 'off',
			'@stylistic/function-paren-newline': 'off',
			'@stylistic/member-delimiter-style': 'off',
			'vue/attributes-order': 'off',
			'vue/new-line-between-multi-line-property': 'off',
			'@stylistic/max-statements-per-line': 'off',
			'@stylistic/indent': 'off',
			'@typescript-eslint/no-unused-vars': 'off',
			'vue/order-in-components': 'off',
			'@stylistic/no-extra-semi': 'off',
			'@stylistic/eol-last': 'off',
			'@stylistic/implicit-arrow-linebreak': 'off',
			'vue/component-options-name-casing': 'off',
			'@stylistic/arrow-parens': 'off',
			'vue/padding-line-between-blocks': 'off',
		},
	},
	{
		name: 'twofactor_gateway/package-json',
		files: ['package.json'],
		rules: {
			'package-json/sort-package-json': 'off',
		},
	},
]
