/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const path = require('path')
const sass = require('sass')
const webpack = require('webpack')
const { VueLoaderPlugin } = require('vue-loader')

const stableComponents = [
	{
		source: 'src/views/AdminSettings.vue',
		importLine: "import { GatewayAdminSettings } from '@lib/twofactor-gateway/components/adminSettings'",
	},
	{
		source: 'src/components/GatewaySection.vue',
		importLine: "import { GatewaySection } from '@lib/twofactor-gateway/components/gatewaySection'",
	},
	{
		source: 'src/components/GatewayInstanceCard.vue',
		importLine: "import { GatewayInstanceCard } from '@lib/twofactor-gateway/components/gatewayInstanceCard'",
	},
	{
		source: 'src/components/GatewayInstanceModal.vue',
		importLine: "import { GatewayInstanceModal } from '@lib/twofactor-gateway/components/gatewayInstanceModal'",
	},
	{
		source: 'src/components/GatewayRoutingModal.vue',
		importLine: "import { GatewayRoutingModal } from '@lib/twofactor-gateway/components/gatewayRoutingModal'",
	},
	{
		source: 'src/components/GatewayTestModal.vue',
		importLine: "import { GatewayTestModal } from '@lib/twofactor-gateway/components/gatewayTestModal'",
	},
]

const componentImportLines = new Map(
	stableComponents.map((component) => [
		path.resolve(__dirname, component.source),
		component.importLine,
	]),
)

module.exports = {
	title: 'Two-Factor Gateway Frontend Reusable Surface',
	pagePerSection: true,
	styleguideDir: 'build/styleguide',
	require: [path.join(__dirname, 'src/styleguide/assets/theme.css')],
	exampleMode: 'collapse',
	usageMode: 'expand',
	skipComponentsWithoutExample: false,
	enhancePreviewApp: path.resolve(__dirname, 'src/styleguide/preview.js'),
	getComponentPathLine(componentPath) {
		const absolutePath = path.resolve(__dirname, componentPath)
		return componentImportLines.get(absolutePath) ?? `import ${path.basename(componentPath, '.vue')} from '${componentPath}'`
	},
	webpackConfig: {
		module: {
			rules: [
				{
					test: /\.vue$/,
					loader: 'vue-loader',
				},
				{
					test: /\.tsx?$/,
					exclude: /node_modules/,
					use: [{
						loader: 'ts-loader',
						options: {
							appendTsSuffixTo: [/\.vue$/],
							transpileOnly: true,
						},
					}],
				},
				{
					test: /\.css$/,
					use: ['style-loader', 'css-loader'],
				},
				{
					test: /\.scss$/,
					use: [
						'style-loader',
						'css-loader',
						{
							loader: 'sass-loader',
							options: {
								implementation: sass,
								api: 'modern',
							},
						},
					],
				},
			],
		},
		plugins: [
			new VueLoaderPlugin(),
			new webpack.ProvidePlugin({
				process: 'process/browser.js',
			}),
		],
		resolve: {
			alias: {
				'@lib/twofactor-gateway': path.resolve(__dirname, 'src/lib/twofactor-gateway'),
				'process/browser': require.resolve('process/browser.js'),
				vue$: 'vue/dist/vue.esm-browser.js',
			},
			fallback: {
				process: require.resolve('process/browser.js'),
			},
			extensions: ['.mjs', '.js', '.ts', '.vue', '.json'],
		},
		optimization: {
			minimize: false,
		},
	},
	sections: [
		{
			name: 'Introduction',
			content: 'src/styleguide/docs/index.md',
			exampleMode: 'hide',
			usageMode: 'hide',
		},
		{
			name: 'Stable root surface',
			content: 'src/styleguide/docs/root-surface.md',
			exampleMode: 'hide',
			usageMode: 'hide',
		},
		{
			name: 'Shared frontend types',
			content: 'src/styleguide/docs/shared-types.md',
			exampleMode: 'hide',
			usageMode: 'hide',
		},
		{
			name: 'Components',
			content: 'src/styleguide/docs/components.md',
			sectionDepth: 1,
			sections: [
				{
					name: 'Containers',
					components: [
						'src/views/AdminSettings.vue',
						'src/components/GatewaySection.vue',
					],
				},
				{
					name: 'Reusable building blocks',
					components: [
						'src/components/GatewayInstanceCard.vue',
						'src/components/GatewayInstanceModal.vue',
						'src/components/GatewayRoutingModal.vue',
						'src/components/GatewayTestModal.vue',
					],
				},
			],
		},
	],
	ribbon: {
		text: 'View on GitHub',
		url: 'https://github.com/nextcloud/twofactor_gateway',
	},
}
