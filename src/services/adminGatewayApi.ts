/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import {
	normalizeGatewayInstance,
	type GatewayInfo,
	type GatewayInstancePayload,
	type GatewayGroup,
	type InteractiveSetupResponse,
	type TestResult,
} from './adminGatewayTypes.ts'

function ocsData<T>(response: { data: unknown }): T {
	const d = response.data as Record<string, Record<string, T>>
	if (d?.ocs?.data) {
		return d.ocs.data
	}
	// Fallback for OCSController DataResponse that returns raw array
	if (Array.isArray(response.data)) {
		return response.data as unknown as T
	}
	throw new Error(`Unexpected OCS response structure: ${JSON.stringify(response.data)}`)
}

/**
 * List all available gateways with their configured instances.
 */
export async function listGateways(): Promise<GatewayInfo[]> {
	const response = await axios.get(generateOcsUrl('/apps/twofactor_gateway/admin/gateways'))
	const gateways = ocsData<GatewayInfo[]>(response)
	return gateways.map((gateway) => ({
		...gateway,
		instances: gateway.instances.map((instance) => normalizeGatewayInstance(instance, gateway.id)),
	}))
}

/**
 * List all assignable groups for instance routing.
 */
export async function listGroups(query = '', limit = 200): Promise<GatewayGroup[]> {
	const response = await axios.get(generateOcsUrl('/apps/twofactor_gateway/admin/groups'), {
		params: { query, limit },
	})
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
	const instance = ocsData<GatewayInstancePayload>(response)
	return normalizeGatewayInstance(instance, gatewayId)
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
	const instance = ocsData<GatewayInstancePayload>(response)
	return normalizeGatewayInstance(instance, gatewayId)
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
	const instance = ocsData<GatewayInstancePayload>(response)
	return normalizeInstance(instance, gatewayId)
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
	input: Record<string, string> = {},
): Promise<InteractiveSetupResponse> {
	const response = await axios.post(
		generateOcsUrl('/apps/twofactor_gateway/admin/gateways/{gateway}/interactive-setup/cancel', {
			gateway: gatewayId,
		}),
		{ sessionId, input },
	)
	return ocsData<InteractiveSetupResponse>(response)
}
