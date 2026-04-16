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
	type?: string
	hidden?: boolean
	min?: number
	max?: number
}

export interface GatewayInstance {
	id: string
	providerId: string
	label: string
	default: boolean
	createdAt: string
	config: Record<string, string>
	isComplete: boolean
	groupIds: string[]
	priority: number
}

export interface GatewayGroup {
	id: string
	displayName: string
}

export interface GatewayProviderDefinition {
	id: string
	name: string
	fields: FieldDefinition[]
}

export interface GatewayInfo {
	id: string
	name: string
	instructions: string
	allowMarkdown: boolean
	fields: FieldDefinition[]
	providerSelector?: FieldDefinition
	providerCatalog?: GatewayProviderDefinition[]
	instances: GatewayInstance[]
}

export interface TestResult {
	success: boolean
	message: string
	accountInfo?: {
		account_name?: string
	}
}

export interface InteractiveSetupResponse {
	status: 'needs_input' | 'pending' | 'done' | 'error' | 'cancelled'
	message?: string
	messageType?: 'info' | 'success' | 'warning' | 'error'
	sessionId?: string
	step?: string
	data?: Record<string, unknown>
	config?: Record<string, string>
}

function ocsData<T>(response: any): T {
	if (response.data?.ocs?.data) {
		return response.data.ocs.data
	}
	// Fallback for OCSController DataResponse that returns raw array
	if (Array.isArray(response.data)) {
		return response.data as T
	}
	throw new Error(`Unexpected OCS response structure: ${JSON.stringify(response.data)}`)
}

/**
 * List all available gateways with their configured instances.
 */
export async function listGateways(): Promise<GatewayInfo[]> {
	const response = await axios.get(generateOcsUrl('/apps/twofactor_gateway/admin/gateways'))
	return ocsData<GatewayInfo[]>(response)
}

/**
 * List all assignable groups for instance routing.
 */
export async function listGroups(): Promise<GatewayGroup[]> {
	const response = await axios.get(generateOcsUrl('/apps/twofactor_gateway/admin/groups'))
	return ocsData<GatewayGroup[]>(response)
}

/**
 * Create a new configuration instance for a gateway.
 */
export async function createInstance(
	gatewayId: string,
	label: string,
	config: Record<string, string>,
	groupIds: string[] = [],
	priority = 0,
): Promise<GatewayInstance> {
	const response = await axios.post(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/instances', { gateway: gatewayId }),
		{ label, config, groupIds, priority },
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
	groupIds: string[] = [],
	priority = 0,
): Promise<GatewayInstance> {
	const response = await axios.put(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/instances/{instanceId}', {
			gateway: gatewayId,
			instanceId,
		}),
		{ label, config, groupIds, priority },
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

export async function startInteractiveSetup(
	gatewayId: string,
	input: Record<string, string>,
): Promise<InteractiveSetupResponse> {
	const response = await axios.post(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/interactive-setup/start', {
			gateway: gatewayId,
		}),
		{ input },
	)
	return ocsData<InteractiveSetupResponse>(response)
}

export async function interactiveSetupStep(
	gatewayId: string,
	sessionId: string,
	action: string,
	input: Record<string, unknown> = {},
): Promise<InteractiveSetupResponse> {
	const response = await axios.post(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/interactive-setup/step', {
			gateway: gatewayId,
		}),
		{ sessionId, action, input },
	)
	return ocsData<InteractiveSetupResponse>(response)
}

export async function cancelInteractiveSetup(
	gatewayId: string,
	sessionId: string,
): Promise<InteractiveSetupResponse> {
	const response = await axios.post(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/interactive-setup/cancel', {
			gateway: gatewayId,
		}),
		{ sessionId },
	)
	return ocsData<InteractiveSetupResponse>(response)
}
