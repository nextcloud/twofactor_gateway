// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { APIRequestContext } from '@playwright/test'

const OCS_HEADERS = {
	'OCS-APIRequest': 'true',
	Accept: 'application/json',
}

function basicAuth(user: string, password: string): string {
	return 'Basic ' + Buffer.from(`${user}:${password}`).toString('base64')
}

function ocsUrl(path: string): string {
	return `./ocs/v2.php${path}?format=json`
}

export interface GatewayInstance {
	id: string
	label: string
	default: boolean
	createdAt: string
	config: Record<string, string>
	isComplete: boolean
}

/**
 * Create a gateway instance via the admin OCS API, using Basic Auth.
 * Returns the created instance.
 */
export async function createGatewayInstance(
	request: APIRequestContext,
	adminUser: string,
	adminPassword: string,
	gatewayId: string,
	label: string,
	config: Record<string, string>,
): Promise<GatewayInstance> {
	const response = await request.post(
		ocsUrl(`/apps/twofactor_gateway/admin/gateways/${gatewayId}/instances`),
		{
			headers: {
				...OCS_HEADERS,
				Authorization: basicAuth(adminUser, adminPassword),
			},
			form: {
				label,
				...Object.fromEntries(
					Object.entries(config).map(([k, v]) => [`config[${k}]`, v]),
				),
			},
			failOnStatusCode: true,
		},
	)
	const body = await response.json() as { ocs: { data: GatewayInstance } }
	return body.ocs.data
}

/**
 * Delete a gateway instance via the admin OCS API.
 * Does not throw if the instance does not exist.
 */
export async function deleteGatewayInstance(
	request: APIRequestContext,
	adminUser: string,
	adminPassword: string,
	gatewayId: string,
	instanceId: string,
): Promise<void> {
	await request.delete(
		ocsUrl(`/apps/twofactor_gateway/admin/gateways/${gatewayId}/instances/${instanceId}`),
		{
			headers: {
				...OCS_HEADERS,
				Authorization: basicAuth(adminUser, adminPassword),
			},
			failOnStatusCode: false,
		},
	)
}

/**
 * List all instances for a gateway via the admin OCS API.
 */
export async function listGatewayInstances(
	request: APIRequestContext,
	adminUser: string,
	adminPassword: string,
	gatewayId: string,
): Promise<GatewayInstance[]> {
	const response = await request.get(
		ocsUrl('/apps/twofactor_gateway/admin/gateways'),
		{
			headers: {
				...OCS_HEADERS,
				Authorization: basicAuth(adminUser, adminPassword),
			},
			failOnStatusCode: true,
		},
	)
	const body = await response.json() as { ocs: { data: Array<{ id: string; instances: GatewayInstance[] }> } }
	const gateway = body.ocs.data.find((g) => g.id === gatewayId)
	return gateway?.instances ?? []
}
