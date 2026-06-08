// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

const SWAGGER_UI_CSS = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css'
const SWAGGER_UI_BUNDLE = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js'
const SWAGGER_UI_PRESET = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js'

function loadStylesheet(href) {
	if (document.querySelector(`link[href="${href}"]`)) {
		return
	}

	const link = document.createElement('link')
	link.rel = 'stylesheet'
	link.href = href
	document.head.append(link)
}

function loadScript(src) {
	return new Promise((resolve, reject) => {
		if (document.querySelector(`script[src="${src}"]`)) {
			resolve()
			return
		}

		const script = document.createElement('script')
		script.src = src
		script.async = false
		script.onload = () => resolve()
		script.onerror = () => reject(new Error(`Failed to load ${src}`))
		document.head.append(script)
	})
}

async function bootSwaggerUi() {
	const target = document.querySelector('#swagger-ui')
	if (!(target instanceof HTMLElement)) {
		return
	}

	const openapiRoot = target.dataset.openapiRoot ?? '../openapi'
	loadStylesheet(SWAGGER_UI_CSS)
	await loadScript(SWAGGER_UI_BUNDLE)
	await loadScript(SWAGGER_UI_PRESET)

	if (typeof SwaggerUIBundle !== 'function') {
		throw new Error('SwaggerUIBundle is not available')
	}

	SwaggerUIBundle({
		urls: [
			{ url: `${openapiRoot}/openapi-administration.json`, name: 'Administration' },
			{ url: `${openapiRoot}/openapi.json`, name: 'Default' },
			{ url: `${openapiRoot}/openapi-full.json`, name: 'Full' },
		],
		'urls.primaryName': 'Administration',
		dom_id: '#swagger-ui',
		deepLinking: true,
		displayRequestDuration: true,
		docExpansion: 'list',
		defaultModelsExpandDepth: -1,
		presets: [
			SwaggerUIBundle.presets.apis,
			SwaggerUIStandalonePreset,
		],
		layout: 'StandaloneLayout',
	})
}

window.addEventListener('load', () => {
	bootSwaggerUi().catch((error) => {
		console.error('Failed to boot OpenAPI explorer', error)
	})
})
