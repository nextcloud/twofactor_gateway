// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { APIRequestContext } from '@playwright/test'

export async function login(
	request: APIRequestContext,
	user: string,
	password: string,
): Promise<void> {
	const tokenResponse = await request.get('./csrftoken', {
		failOnStatusCode: true,
	})

	const { token: requesttoken } = await tokenResponse.json() as { token: string }
	const origin = tokenResponse.url().replace(/index\.php.*/, '')

	const loginResponse = await request.post('./login', {
		form: {
			user,
			password,
			requesttoken,
		},
		headers: {
			Origin: origin,
		},
		maxRedirects: 0,
		failOnStatusCode: false,
	})

	if (!loginResponse.headers()['x-user-id']) {
		throw new Error(`Login failed for user "${user}" (status ${loginResponse.status()})`)
	}

	await request.get('./apps/dashboard/', {
		failOnStatusCode: true,
	})
}
