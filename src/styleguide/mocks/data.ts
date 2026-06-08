// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type {
	FieldDefinition,
	GatewayGroup,
	GatewayInfo,
	GatewayInstance,
} from '../../lib/twofactor-gateway/types/gateway.ts'

function clone<T>(value: T): T {
	return JSON.parse(JSON.stringify(value)) as T
}

const acmeSmsFields: FieldDefinition[] = [
	{
		field: 'sender_id',
		prompt: 'Sender ID',
		helper: 'Visible sender label used in outgoing messages.',
		default: '',
		optional: false,
		type: 'text',
		hidden: false,
	},
	{
		field: 'api_token',
		prompt: 'API token',
		helper: 'Secret credential used to authenticate requests.',
		default: '',
		optional: false,
		type: 'secret',
		hidden: false,
	},
	{
		field: 'sandbox_mode',
		prompt: 'Sandbox mode',
		helper: 'Enable this when you want to test against the provider sandbox.',
		default: '0',
		optional: true,
		type: 'boolean',
		hidden: false,
	},
	{
		field: 'request_timeout',
		prompt: 'Timeout (seconds)',
		helper: 'Maximum time the gateway waits for the upstream provider.',
		default: '30',
		optional: true,
		type: 'integer',
		hidden: false,
		min: 5,
		max: 120,
	},
]

const chatBridgeFields: FieldDefinition[] = [
	{
		field: 'endpoint',
		prompt: 'Endpoint URL',
		helper: 'Base URL of the chat bridge instance.',
		default: '',
		optional: false,
		type: 'text',
		hidden: false,
	},
	{
		field: 'channel',
		prompt: 'Channel',
		helper: 'Destination room or channel used for notifications.',
		default: '',
		optional: false,
		type: 'text',
		hidden: false,
	},
	{
		field: 'signing_secret',
		prompt: 'Signing secret',
		helper: 'Optional signature secret for webhook-style integrations.',
		default: '',
		optional: true,
		type: 'secret',
		hidden: false,
	},
]

const sampleGroups: GatewayGroup[] = [
	{ id: 'finance', displayName: 'Finance' },
	{ id: 'support', displayName: 'Support' },
	{ id: 'sales', displayName: 'Sales' },
	{ id: 'partners', displayName: 'Partners' },
]

const acmeSmsInstances: GatewayInstance[] = [
	{
		id: 'acme-primary',
		providerId: 'acme_sms',
		label: 'Primary SMS gateway',
		default: true,
		createdAt: '2026-06-01T08:15:00Z',
		config: {
			sender_id: 'Acme App',
			api_token: 'live_demo_token',
			sandbox_mode: '0',
			request_timeout: '30',
		},
		isComplete: true,
		groupIds: ['finance', 'support'],
		priority: 3,
	},
	{
		id: 'acme-sandbox',
		providerId: 'acme_sms',
		label: 'Sandbox tenant',
		default: false,
		createdAt: '2026-06-02T10:45:00Z',
		config: {
			sender_id: 'Sandbox',
			api_token: '',
			sandbox_mode: '1',
			request_timeout: '15',
		},
		isComplete: false,
		groupIds: ['sales'],
		priority: 2,
	},
]

const chatBridgeInstances: GatewayInstance[] = [
	{
		id: 'chatbridge-ops',
		providerId: 'chatbridge',
		label: 'Operations chat bridge',
		default: false,
		createdAt: '2026-06-03T14:30:00Z',
		config: {
			endpoint: 'https://chat.example.test',
			channel: '#security-codes',
			signing_secret: 'bridge-demo-secret',
		},
		isComplete: true,
		groupIds: ['partners'],
		priority: 1,
	},
]

const sampleGateways: GatewayInfo[] = [
	{
		id: 'acme_sms',
		name: 'Acme SMS',
		instructions: 'Use this gateway when you want to send verification codes through a generic SMS provider.',
		allowMarkdown: false,
		fields: acmeSmsFields,
		instances: acmeSmsInstances,
	},
	{
		id: 'chatbridge',
		name: 'Chat Bridge',
		instructions: 'Demo gateway used in the styleguide to show a non-SMS transport with simpler fields.',
		allowMarkdown: false,
		fields: chatBridgeFields,
		instances: chatBridgeInstances,
	},
]

export function cloneStyleguideGroups(): GatewayGroup[] {
	return clone(sampleGroups)
}

export function cloneStyleguideGateways(): GatewayInfo[] {
	return clone(sampleGateways)
}

export function cloneGatewayById(gatewayId: string): GatewayInfo {
	const gateway = sampleGateways.find((entry) => entry.id === gatewayId) ?? sampleGateways[0]
	return clone(gateway)
}

export function cloneInstanceById(gatewayId: string, instanceId: string): GatewayInstance {
	const gateway = cloneGatewayById(gatewayId)
	return clone(gateway.instances.find((instance) => instance.id === instanceId) ?? gateway.instances[0])
}
