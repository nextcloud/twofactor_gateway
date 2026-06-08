// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

export function vueDocsBlockPlugin() {
	return {
		name: 'twofactor-gateway-vue-docs-block',
		transform(code, id) {
			if (!id.includes('type=docs') && !id.includes('lang.docs')) {
				return null
			}

			const docs = JSON.stringify(code.trim())
			return {
				code: `export default function attachDocs(Component) {\n\tComponent.__docs = ${docs}\n}`,
				map: null,
			}
		},
	}
}
