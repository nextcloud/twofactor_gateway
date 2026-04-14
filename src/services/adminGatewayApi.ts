/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export interface FieldDefinition {
	field: string
	prompt: string
	default: string
	optional: boolean
}

export interface GatewayInstance {
	id: string
	label: string
	default: boolean
	createdAt: string
	config: Record<string, string>
	isComplete: boolean
}

export interface GatewayInfo {
	id: string
	name: string
	instructions: string
	allowMarkdown: boolean
	fields: FieldDefinition[]
	instances: GatewayInstance[]
}

export interface TestResult {
	success: boolean
	message: string
}

function ocsData<T>(response: { data: { ocs: { data: T } } }): T {
	return response.data.ocs.data
}

/**
 * List all available gateways with their configured instances.
 */
export async function listGateways(): Promise<GatewayInfo[]> {
	const response = await axios.get(generateOcsUrl('/apps/twofactor_gateway/admin/gateways'))
	return ocsData<GatewayInfo[]>(response)
}

/**
 * Create a new configuration instance for a gateway.
 */
export async function createInstance(
	gatewayId: string,
	label: string,
	config: Record<string, string>,
): Promise<GatewayInstance> {
	const response = await axios.post(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/instances', { gateway: gatewayId }),
		{ label, config },
	)
	return ocsData<GatewayInstance>(response)
}

/**
 * Get a single configuration instance.
 */
export async function getInstance(gatewayId: string, instanceId: string): Promise<GatewayInstance> {
	const response = await axios.get(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/instances/{instanceId}', {
			gateway: gatewayId,
			instanceId,
		}),
	)
	return ocsData<GatewayInstance>(response)
}

/**
 * Update an existing configuration instance.
 */
export async function updateInstance(
	gatewayId: string,
	instanceId: string,
	label: string,
	config: Record<string, string>,
): Promise<GatewayInstance> {
	const response = await axios.put(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/instances/{instanceId}', {
			gateway: gatewayId,
			instanceId,
		}),
		{ label, config },
	)
	return ocsData<GatewayInstance>(response)
}

/**
 * Delete a configuration instance.
 */
export async function deleteInstance(gatewayId: string, instanceId: string): Promise<void> {
	await axios.delete(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/instances/{instanceId}', {
			gateway: gatewayId,
			instanceId,
		}),
	)
}

/**
 * Promote an instance to be the default for its gateway.
 */
export async function setDefaultInstance(gatewayId: string, instanceId: string): Promise<void> {
	await axios.post(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/instances/{instanceId}/default', {
			gateway: gatewayId,
			instanceId,
		}),
	)
}

/**
 * Send a test message via a specific configuration instance.
 */
export async function testInstance(
	gatewayId: string,
	instanceId: string,
	identifier: string,
): Promise<TestResult> {
	const response = await axios.post(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/instances/{instanceId}/test', {
			gateway: gatewayId,
			instanceId,
		}),
		{ identifier },
	)
	return ocsData<TestResult>(response)
}
