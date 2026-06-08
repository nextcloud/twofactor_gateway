/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Component } from 'vue'

type ViteImportMeta = ImportMeta & {
	glob: <T = unknown>(
		pattern: string,
		options: { eager: true; import: 'default' },
	) => Record<string, T>
}

type WebpackModule<T> = {
	default: T
}

type WebpackRequireContext<T> = {
	(key: string): T
	keys(): string[]
}

type WebpackImportMeta = ImportMeta & {
	webpackContext?: <T = unknown>(
		request: string,
		options?: {
			recursive?: boolean
			regExp?: RegExp
			mode?: 'sync' | 'eager' | 'weak' | 'lazy' | 'lazy-once'
		},
	) => WebpackRequireContext<T>
}

function discoverSetupPanels(): Record<string, Component> {
	const viteGlob = (import.meta as Partial<ViteImportMeta>).glob
	if (typeof viteGlob === 'function') {
		return viteGlob<Component>('./*/SetupPanel.vue', {
			eager: true,
			import: 'default',
		})
	}

	const webpackContext = (import.meta as Partial<WebpackImportMeta>).webpackContext
	if (typeof webpackContext === 'function') {
		const context = webpackContext<WebpackModule<Component>>('./', {
			recursive: true,
			regExp: /^\.\/[^/]+\/SetupPanel\.vue$/,
			mode: 'sync',
		})
		return Object.fromEntries(
			context.keys().map((key) => {
				const module = context(key)
				return [key, module.default] as const
			}),
		)
	}

	return {}
}

const discoveredSetupPanels = discoverSetupPanels()

const providerSetupPanels: Record<string, Component> = Object.fromEntries(
	Object.entries(discoveredSetupPanels)
		.map(([path, component]) => {
			const match = path.match(/^\.\/([^/]+)\/SetupPanel\.vue$/)
			if (!match) {
				return null
			}

			return [match[1], component] as const
		})
		.filter((entry): entry is readonly [string, Component] => entry !== null),
)

export function resolveGatewaySetupPanel(providerId: string): Component | null {
	return providerSetupPanels[providerId] ?? null
}
