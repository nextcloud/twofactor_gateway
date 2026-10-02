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
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/tag-lines': 'off',
			'vue/first-attribute-linebreak': 'off',
			'vue/max-attributes-per-line': 'off',

			// Preserve the effective v8 behavior while adopting the v9 flat config.
			'no-console': ['error', { allow: ['error', 'warn', 'info', 'debug'] }],
			'vue/attribute-hyphenation': ['error', 'always'],
			'vue/custom-event-name-casing': ['error', 'kebab-case', {
				ignores: ['/^[a-z]+(?:-[a-z]+)*:[a-z]+(?:-[a-z]+)*$/u'],
			}],
			'vue/v-on-event-hyphenation': ['error', 'always'],

			// New v9 rules require a broad code-style migration. Keep them out of
			// this dependency bump and migrate them separately.
			'package-json/sort-package-json': 'off',
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
			'@typescript-eslint/ban-types': 'off',
			'vue/component-options-name-casing': 'off',
			'@stylistic/arrow-parens': 'off',
			'vue/padding-line-between-blocks': 'off',

			// Keep newly detected deprecations visible without blocking this bump.
			'@nextcloud/no-deprecated-library-props': 'warn',
			'@nextcloud/l10n-enforce-ellipsis': 'warn',
			'jsdoc/check-tag-names': 'warn',
		},
	},
]
