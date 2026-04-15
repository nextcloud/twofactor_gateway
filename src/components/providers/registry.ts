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

const discoveredSetupPanels = (import.meta as ViteImportMeta).glob<Component>('./*/SetupPanel.vue', {
	eager: true,
	import: 'default',
})

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
