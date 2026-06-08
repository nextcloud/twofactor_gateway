// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type {
	FieldDefinition,
	GatewayGroup,
	GatewayInfo,
	GatewayInstance,
	InteractiveSetupResponse,
	TestResult,
} from '../types/gateway.ts'
import {
	normalizeGatewayAdminSnapshot,
	type GatewayAdminInitialData,
	type GatewayAdminSnapshot,
} from './gatewayAdminSnapshot.ts'

type LocalInteractiveSetupSession = {
	gatewayId: string
	config: Record<string, string>
}

type LocalGatewayAdminState = {
	gateways: GatewayInfo[]
	groups: GatewayGroup[]
	instanceCounter: number
	sessionCounter: number
	sessions: Map<string, LocalInteractiveSetupSession>
}

type TestInstanceContext = {
	gatewayId: string
	instanceId: string
	identifier: string
}

type StartInteractiveSetupContext = {
	gatewayId: string
	sessionId: string
	input: Record<string, string>
	config: Record<string, string>
}

type InteractiveSetupStepContext = {
	gatewayId: string
	sessionId: string
	action: string
	input: Record<string, unknown>
	config: Record<string, string>
}

type CancelInteractiveSetupContext = {
	gatewayId: string
	sessionId: string
	input: Record<string, string>
	config: Record<string, string> | null
}

export type LocalGatewayAdminApi = {
	listAdminScreen(groupLimit?: number): Promise<GatewayAdminInitialData>
	listGateways(): Promise<GatewayInfo[]>
	listGroups(query?: string, limit?: number): Promise<GatewayGroup[]>
	createInstance(
		gatewayId: string,
		label: string,
		config: Record<string, string>,
		groupIds?: string[],
		priority?: number,
	): Promise<GatewayInstance>
	getInstance(gatewayId: string, instanceId: string): Promise<GatewayInstance>
	updateInstance(
		gatewayId: string,
		instanceId: string,
		label: string,
		config: Record<string, string>,
		groupIds?: string[],
		priority?: number,
	): Promise<GatewayInstance>
	deleteInstance(gatewayId: string, instanceId: string): Promise<void>
	setDefaultInstance(gatewayId: string, instanceId: string): Promise<void>
	testInstance(gatewayId: string, instanceId: string, identifier: string): Promise<TestResult>
	startInteractiveSetup(gatewayId: string, input: Record<string, string>): Promise<InteractiveSetupResponse>
	interactiveSetupStep(
		gatewayId: string,
		sessionId: string,
		action: string,
		input?: Record<string, unknown>,
	): Promise<InteractiveSetupResponse>
	cancelInteractiveSetup(
		gatewayId: string,
		sessionId: string,
		input?: Record<string, string>,
	): Promise<InteractiveSetupResponse>
}

export type LocalGatewayAdminApiOptions = {
	createSnapshot: () => GatewayAdminSnapshot
	testInstance?: (context: TestInstanceContext) => TestResult | Promise<TestResult>
	startInteractiveSetup?: (context: StartInteractiveSetupContext) => InteractiveSetupResponse | Promise<InteractiveSetupResponse>
	interactiveSetupStep?: (context: InteractiveSetupStepContext) => InteractiveSetupResponse | Promise<InteractiveSetupResponse>
	cancelInteractiveSetup?: (context: CancelInteractiveSetupContext) => InteractiveSetupResponse | Promise<InteractiveSetupResponse>
}

function clone<T>(value: T): T {
	return JSON.parse(JSON.stringify(value)) as T
}

function normalizeConfig(config: Record<string, unknown> = {}): Record<string, string> {
	return Object.fromEntries(
		Object.entries(config).map(([key, value]) => [key, String(value ?? '')]),
	)
}

function getGatewayFields(gateway: GatewayInfo, config: Record<string, string>): FieldDefinition[] {
	if (!gateway.providerCatalog || gateway.providerCatalog.length === 0) {
		return gateway.fields
	}

	const selectorField = gateway.providerSelector?.field ?? 'provider'
	const providerId = config[selectorField]
	const provider = gateway.providerCatalog.find((entry) => entry.id === providerId)
	return provider?.fields ?? gateway.fields
}

function isInstanceComplete(gateway: GatewayInfo, config: Record<string, string>): boolean {
	const fields = getGatewayFields(gateway, config)
	return fields.every((field) => field.optional || String(config[field.field] ?? '').trim() !== '')
}

function buildSetupResponse(
	status: InteractiveSetupResponse['status'],
	message: string,
	messageType: InteractiveSetupResponse['messageType'],
	overrides: Partial<InteractiveSetupResponse> = {},
): InteractiveSetupResponse {
	return {
		status,
		message,
		messageType,
		...overrides,
	}
}

function createDefaultTestResult({ gatewayId, instanceId, identifier }: TestInstanceContext): TestResult {
	const trimmed = identifier.trim()
	if (trimmed.toLowerCase().includes('fail')) {
		return {
			success: false,
			message: `Preview mock: the test for ${gatewayId}/${instanceId} failed intentionally for identifier "${trimmed}".`,
		}
	}

	return {
		success: true,
		message: `Preview mock: sent a verification message to ${trimmed} using ${gatewayId}/${instanceId}.`,
		accountInfo: {
			account_name: trimmed,
			account_avatar_url: '',
		},
	}
}

export function createLocalGatewayAdminApi(options: LocalGatewayAdminApiOptions) {
	const createState = (): LocalGatewayAdminState => {
		const snapshot = normalizeGatewayAdminSnapshot(options.createSnapshot()) ?? { gateways: [], groups: [], items: [] }
		const instanceCount = snapshot.gateways.reduce((count, gateway) => count + gateway.instances.length, 0)
		return {
			gateways: clone(snapshot.gateways),
			groups: clone(snapshot.groups),
			instanceCounter: instanceCount,
			sessionCounter: 0,
			sessions: new Map<string, LocalInteractiveSetupSession>(),
		}
	}

	let state = createState()

	const reset = () => {
		state = createState()
	}

	const getSnapshot = (): { gateways: GatewayInfo[]; groups: GatewayGroup[] } => ({
		gateways: clone(state.gateways),
		groups: clone(state.groups),
	})

	const findGateway = (gatewayId: string): GatewayInfo => {
		const gateway = state.gateways.find((entry) => entry.id === gatewayId)
		if (!gateway) {
			throw new Error(`Unknown gateway: ${gatewayId}`)
		}

		return gateway
	}

	const findInstance = (gatewayId: string, instanceId: string): GatewayInstance => {
		const instance = findGateway(gatewayId).instances.find((entry) => entry.id === instanceId)
		if (!instance) {
			throw new Error(`Unknown instance: ${instanceId}`)
		}

		return instance
	}

	const nextInstanceId = (gatewayId: string): string => {
		state.instanceCounter += 1
		return `${gatewayId}-demo-${state.instanceCounter}`
	}

	const nextSessionId = (): string => {
		state.sessionCounter += 1
		return `local-session-${state.sessionCounter}`
	}

	const resolveProviderId = (gateway: GatewayInfo, config: Record<string, string>): string => {
		if (!gateway.providerCatalog || gateway.providerCatalog.length === 0) {
			return gateway.id
		}

		const selectorField = gateway.providerSelector?.field ?? 'provider'
		const selectedProviderId = config[selectorField]
		return gateway.providerCatalog.some((entry) => entry.id === selectedProviderId)
			? selectedProviderId
			: gateway.id
	}

	const getSession = (gatewayId: string, sessionId: string): LocalInteractiveSetupSession | null => {
		const session = state.sessions.get(sessionId)
		if (!session || session.gatewayId !== gatewayId) {
			return null
		}

		return session
	}

	const api: LocalGatewayAdminApi = {
		async listAdminScreen(groupLimit = 200): Promise<GatewayAdminInitialData> {
			const normalized = normalizeGatewayAdminSnapshot({
				gateways: clone(state.gateways),
				groups: clone(state.groups).slice(0, groupLimit),
			})

			if (normalized === null) {
				throw new Error('Unexpected empty local admin screen payload')
			}

			return normalized
		},

		async listGateways(): Promise<GatewayInfo[]> {
			return clone(state.gateways)
		},

		async listGroups(query = '', limit = 200): Promise<GatewayGroup[]> {
			const normalizedQuery = query.trim().toLowerCase()
			const filtered = state.groups.filter((group) => {
				if (normalizedQuery === '') {
					return true
				}

				return group.displayName.toLowerCase().includes(normalizedQuery)
					|| group.id.toLowerCase().includes(normalizedQuery)
			})

			return clone(filtered.slice(0, limit))
		},

		async createInstance(
			gatewayId: string,
			label: string,
			config: Record<string, string>,
			groupIds: string[] = [],
			priority = 0,
		): Promise<GatewayInstance> {
			const gateway = findGateway(gatewayId)
			const normalizedConfig = normalizeConfig(config)
			const instance: GatewayInstance = {
				id: nextInstanceId(gatewayId),
				providerId: resolveProviderId(gateway, normalizedConfig),
				label,
				default: gateway.instances.length === 0,
				createdAt: new Date().toISOString(),
				config: normalizedConfig,
				isComplete: isInstanceComplete(gateway, normalizedConfig),
				groupIds: [...groupIds].sort(),
				priority,
			}

			gateway.instances.push(instance)
			return clone(instance)
		},

		async getInstance(gatewayId: string, instanceId: string): Promise<GatewayInstance> {
			return clone(findInstance(gatewayId, instanceId))
		},

		async updateInstance(
			gatewayId: string,
			instanceId: string,
			label: string,
			config: Record<string, string>,
			groupIds: string[] = [],
			priority = 0,
		): Promise<GatewayInstance> {
			const gateway = findGateway(gatewayId)
			const instance = findInstance(gatewayId, instanceId)
			const normalizedConfig = normalizeConfig(config)

			Object.assign(instance, {
				providerId: resolveProviderId(gateway, normalizedConfig),
				label,
				config: normalizedConfig,
				groupIds: [...groupIds].sort(),
				priority,
				isComplete: isInstanceComplete(gateway, normalizedConfig),
			})

			return clone(instance)
		},

		async deleteInstance(gatewayId: string, instanceId: string): Promise<void> {
			const gateway = findGateway(gatewayId)
			const index = gateway.instances.findIndex((entry) => entry.id === instanceId)
			if (index === -1) {
				return
			}

			const [removed] = gateway.instances.splice(index, 1)
			if (removed?.default && gateway.instances.length > 0) {
				gateway.instances[0].default = true
			}
		},

		async setDefaultInstance(gatewayId: string, instanceId: string): Promise<void> {
			for (const instance of findGateway(gatewayId).instances) {
				instance.default = instance.id === instanceId
			}
		},

		async testInstance(gatewayId: string, instanceId: string, identifier: string): Promise<TestResult> {
			if (options.testInstance) {
				return await options.testInstance({ gatewayId, instanceId, identifier })
			}

			return createDefaultTestResult({ gatewayId, instanceId, identifier })
		},

		async startInteractiveSetup(gatewayId: string, input: Record<string, string>): Promise<InteractiveSetupResponse> {
			const sessionId = nextSessionId()
			const config = normalizeConfig(input)
			state.sessions.set(sessionId, { gatewayId, config })

			if (options.startInteractiveSetup) {
				return await options.startInteractiveSetup({ gatewayId, sessionId, input, config: clone(config) })
			}

			return buildSetupResponse(
				'needs_input',
				'Local gateway preview: guided setup is simulated with client-side data.',
				'info',
				{
					config,
					sessionId,
					step: 'local-preview',
				},
			)
		},

		async interactiveSetupStep(
			gatewayId: string,
			sessionId: string,
			action: string,
			input: Record<string, unknown> = {},
		): Promise<InteractiveSetupResponse> {
			const session = getSession(gatewayId, sessionId)
			if (!session) {
				return buildSetupResponse(
					'error',
					'Local gateway preview: unknown guided setup session.',
					'error',
				)
			}

			Object.assign(session.config, normalizeConfig(input))

			if (options.interactiveSetupStep) {
				return await options.interactiveSetupStep({
					gatewayId,
					sessionId,
					action,
					input,
					config: clone(session.config),
				})
			}

			return buildSetupResponse(
				'done',
				`Local gateway preview: completed action "${action}".`,
				'success',
				{
					config: clone(session.config),
					sessionId,
					step: action,
				},
			)
		},

		async cancelInteractiveSetup(
			gatewayId: string,
			sessionId: string,
			input: Record<string, string> = {},
		): Promise<InteractiveSetupResponse> {
			const session = getSession(gatewayId, sessionId)
			const config = normalizeConfig(input)

			if (session) {
				Object.assign(session.config, config)
				state.sessions.delete(sessionId)
			}

			const sessionConfig = session ? clone(session.config) : null
			if (options.cancelInteractiveSetup) {
				return await options.cancelInteractiveSetup({
					gatewayId,
					sessionId,
					input,
					config: sessionConfig,
				})
			}

			return buildSetupResponse(
				'cancelled',
				'Local gateway preview: guided setup cancelled.',
				'warning',
				{
					config: sessionConfig ?? config,
					sessionId,
				},
			)
		},
	}

	return {
		api,
		getSnapshot,
		reset,
	}
}
