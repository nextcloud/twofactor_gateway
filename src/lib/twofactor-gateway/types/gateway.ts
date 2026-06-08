// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

export interface FieldDefinition {
	field: string
	prompt: string
	helper?: string
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

export interface GatewayAdminAllowedActions {
	canView: boolean
	canCreateInstances: boolean
	canEditInstances: boolean
	canDeleteInstances: boolean
	canSetDefaultInstances: boolean
	canManageRouting: boolean
	canTestInstances: boolean
	canReorderInstances: boolean
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
		account_avatar_url?: string
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

export type GatewayInstancePayload = Partial<GatewayInstance> & {
	id: string
	label: string
	default: boolean
	createdAt: string
	config: Record<string, string>
	isComplete: boolean
}

export function normalizeGatewayInstance(instance: GatewayInstancePayload, fallbackProviderId: string): GatewayInstance {
	return {
		id: instance.id,
		providerId: instance.providerId ?? fallbackProviderId,
		label: instance.label,
		default: instance.default,
		createdAt: instance.createdAt,
		config: instance.config,
		isComplete: instance.isComplete,
		groupIds: Array.isArray(instance.groupIds) ? instance.groupIds : [],
		priority: typeof instance.priority === 'number' ? instance.priority : 0,
	}
}
