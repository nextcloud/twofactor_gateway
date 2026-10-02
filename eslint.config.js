/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'

const plugins = Object.assign({}, ...recommended.map((config) => config.plugins ?? {}))

export default [
	...recommended,
	{
		name: 'twofactor_gateway/plugins',
		plugins,
	},
	{
		name: 'twofactor_gateway/ignores',
		ignores: [
			'doc/assets/openapi-explorer.js',
			'src/types/openapi/openapi.ts',
		],
	},
	{
		name: 'twofactor_gateway/base',
		files: ['**/*.{js,mjs,cjs,ts,mts,cts,tsx,vue}'],
		languageOptions: {
			globals: {
				appName: 'writable',
			},
		},
		rules: {
			// Preserve the effective v8 console behavior.
			'no-console': ['error', { allow: ['error', 'warn', 'info', 'debug'] }],

			// New v9 style rules are migrated separately from this dependency bump.
			'import-extensions/extensions': 'off',
			'import-extensions/ban-inline-type-imports': 'off',
			'perfectionist/sort-imports': 'off',
			'perfectionist/sort-named-imports': 'off',
			'antfu/top-level-function': 'off',
			'@stylistic/exp-list-style': 'off',
			'@stylistic/function-call-argument-newline': 'off',
			'@stylistic/function-paren-newline': 'off',
			'@stylistic/member-delimiter-style': 'off',
			'@stylistic/max-statements-per-line': 'off',
			'@stylistic/indent': 'off',
			'@stylistic/no-extra-semi': 'off',
			'@stylistic/eol-last': 'off',
			'@stylistic/implicit-arrow-linebreak': 'off',
			'@stylistic/arrow-parens': 'off',
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/tag-lines': 'off',
			'jsdoc/check-tag-names': 'warn',
		},
	},
	{
		name: 'twofactor_gateway/typescript',
		files: ['**/*.{ts,mts,cts,tsx,vue}'],
		rules: {
			'@typescript-eslint/no-unused-vars': 'off',
			'@typescript-eslint/ban-types': 'off',
		},
	},
	{
		name: 'twofactor_gateway/vue',
		files: ['**/*.vue'],
		rules: {
			'vue/first-attribute-linebreak': 'off',
			'vue/max-attributes-per-line': 'off',

			// Preserve the v8 kebab-case conventions.
			'vue/attribute-hyphenation': ['error', 'always'],
			'vue/custom-event-name-casing': ['error', 'kebab-case', {
				ignores: ['/^[a-z]+(?:-[a-z]+)*:[a-z]+(?:-[a-z]+)*$/u'],
			}],
			'vue/v-on-event-hyphenation': ['error', 'always'],

			// New v9 Vue style rules are migrated separately.
			'vue/attributes-order': 'off',
			'vue/new-line-between-multi-line-property': 'off',
			'vue/order-in-components': 'off',
			'vue/component-options-name-casing': 'off',
			'vue/padding-line-between-blocks': 'off',

			// Keep newly detected deprecations visible without blocking this bump.
			'@nextcloud/no-deprecated-library-props': 'warn',
			'@nextcloud/l10n-enforce-ellipsis': 'warn',
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
