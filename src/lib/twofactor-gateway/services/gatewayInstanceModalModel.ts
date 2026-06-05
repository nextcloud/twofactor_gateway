// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { FieldDefinition, GatewayInfo, GatewayProviderDefinition } from '../types/gateway.ts'

const WIZARD_BOOTSTRAP_FIELDS = new Set(['base_url', 'username', 'password', 'device_name'])
const GUIDED_SETUP_REQUIRED_FIELDS: Record<string, string[]> = {
	signal: ['url'],
	gowhatsapp: ['base_url', 'device_name', 'username', 'password'],
	telegram_client: ['api_id', 'api_hash', 'madeline_log_enabled', 'madeline_log_path'],
	whatsappbusiness: ['access_token', 'api_version', 'waba_id'],
}

type TranslateFn = (text: string, parameters?: Record<string, string | number>) => string

type ValidationInput = {
	label: string
	isEditing: boolean
	selectedGateway?: GatewayInfo
	hasProviderCatalog: boolean
	effectiveCatalogProviderId: string
	providerSelectorFieldName: string
	fieldsToValidate: FieldDefinition[]
	config: Record<string, string>
	t: TranslateFn
}

export function resolveGatewayId(params: {
	gatewayId: string
	selectedGatewayId: string
	isEditing: boolean
	gateways: GatewayInfo[]
	config: Record<string, string>
	instanceId: string
}): string {
	if (params.gatewayId) {
		return params.gatewayId
	}
	if (params.selectedGatewayId) {
		return params.selectedGatewayId
	}
	if (!params.isEditing) {
		return ''
	}

	const hints: string[] = []
	const providerFromConfig = (params.config.provider ?? '').trim()
	if (providerFromConfig !== '') {
		hints.push(providerFromConfig)
	}

	if (params.instanceId.includes(':')) {
		const fromPrefix = params.instanceId.split(':', 1)[0].trim()
		if (fromPrefix !== '') {
			hints.push(fromPrefix)
		}
	}

	for (const hint of hints) {
		const direct = params.gateways.find((gateway) => gateway.id === hint)
		if (direct) {
			return direct.id
		}

		const parent = params.gateways.find((gateway) =>
			(gateway.providerCatalog ?? []).some((provider) => provider.id === hint),
		)
		if (parent) {
			return parent.id
		}
	}

	if (params.gateways.length > 0) {
		return params.gateways[0].id
	}

	return ''
}

export function normalizeProviderCatalog(rawCatalog: GatewayProviderDefinition[] | undefined): GatewayProviderDefinition[] {
	const byId = new Map<string, GatewayProviderDefinition>()
	const byLabel = new Set<string>()

	for (const provider of rawCatalog ?? []) {
		if (!provider || typeof provider.id !== 'string' || provider.id.trim() === '') {
			continue
		}
		if (byId.has(provider.id)) {
			continue
		}

		const normalizedLabel = String(provider.name ?? '').trim().toLowerCase()
		if (normalizedLabel !== '' && byLabel.has(normalizedLabel)) {
			continue
		}
		if (normalizedLabel !== '') {
			byLabel.add(normalizedLabel)
		}

		byId.set(provider.id, provider)
	}

	return Array.from(byId.values())
}

export function resolveEffectiveCatalogProviderId(params: {
	hasProviderCatalog: boolean
	catalog: GatewayProviderDefinition[]
	selectedCatalogProviderId: string
	config: Record<string, string>
	providerSelectorFieldName: string
	instanceId: string
	isEditing: boolean
}): string {
	if (!params.hasProviderCatalog) {
		return ''
	}

	const selected = params.selectedCatalogProviderId.trim()
	if (selected !== '') {
		return selected
	}

	const fromConfig = (params.config[params.providerSelectorFieldName] ?? '').trim()
	if (fromConfig !== '') {
		return fromConfig
	}

	if (params.instanceId.includes(':')) {
		const fromPrefix = params.instanceId.split(':', 1)[0].trim()
		if (fromPrefix !== '' && params.catalog.some((provider) => provider.id === fromPrefix)) {
			return fromPrefix
		}
	}

	if (params.catalog.length === 1) {
		return params.catalog[0].id
	}

	if (params.isEditing && params.catalog.length > 0) {
		return params.catalog[0].id
	}

	return ''
}

export function resolveCurrentFields(params: {
	selectedGateway?: GatewayInfo
	isEditing: boolean
	config: Record<string, string>
	hasProviderCatalog: boolean
	catalog: GatewayProviderDefinition[]
	effectiveCatalogProviderId: string
}): FieldDefinition[] {
	if (!params.selectedGateway && params.isEditing) {
		return Object.keys(params.config)
			.filter((fieldName) => fieldName !== 'provider')
			.map((fieldName) => ({
				field: fieldName,
				prompt: fieldName,
				default: '',
				optional: true,
				type: 'text',
				hidden: false,
			}))
			.filter((field) => !field.hidden)
	}

	if (!params.hasProviderCatalog) {
		return (params.selectedGateway?.fields ?? []).filter((field) => !field.hidden)
	}

	const provider = params.catalog.find((item) => item.id === params.effectiveCatalogProviderId)
	return (provider?.fields ?? []).filter((field) => !field.hidden)
}

export function resolveVisibleFields(currentFields: FieldDefinition[], showWizardFirstFlow: boolean): FieldDefinition[] {
	if (!showWizardFirstFlow) {
		return currentFields
	}

	return currentFields.filter((field) => WIZARD_BOOTSTRAP_FIELDS.has(field.field))
}

export function resolveFieldsToValidate(currentFields: FieldDefinition[], showWizardFirstFlow: boolean): FieldDefinition[] {
	if (showWizardFirstFlow) {
		return []
	}

	return currentFields
}

export function canUseGuidedSetupPanel(providerId: string, currentFields: FieldDefinition[]): boolean {
	const requiredFields = GUIDED_SETUP_REQUIRED_FIELDS[providerId]
	if (!requiredFields) {
		return true
	}

	const fieldNames = new Set(currentFields.map((field) => field.field))
	return requiredFields.every((fieldName) => fieldNames.has(fieldName))
}

export function computeCatalogSelectionState(params: {
	selectedGateway?: GatewayInfo
	catalog: GatewayProviderDefinition[]
	config: Record<string, string>
	instanceId: string
}): { selectedCatalogProviderId: string; config: Record<string, string> } {
	const gateway = params.selectedGateway
	const catalog = params.catalog
	if (!gateway || catalog.length === 0) {
		return { selectedCatalogProviderId: '', config: params.config }
	}

	const selectorFieldName = gateway.providerSelector?.field ?? 'provider'
	if (catalog.length === 1) {
		const onlyProviderId = String(catalog[0]?.id ?? '')
		return {
			selectedCatalogProviderId: onlyProviderId,
			config: {
				...params.config,
				[selectorFieldName]: onlyProviderId,
			},
		}
	}

	const fromConfig = (params.config[selectorFieldName] ?? '').trim()
	if (fromConfig !== '') {
		return { selectedCatalogProviderId: fromConfig, config: params.config }
	}

	if (params.instanceId.includes(':')) {
		const prefix = params.instanceId.split(':', 1)[0].trim()
		if (prefix !== '' && catalog.some((provider) => provider.id === prefix)) {
			return {
				selectedCatalogProviderId: prefix,
				config: {
					...params.config,
					[selectorFieldName]: prefix,
				},
			}
		}
	}

	return { selectedCatalogProviderId: '', config: params.config }
}

export function validateGatewayInstanceForm(input: ValidationInput): Record<string, string> {
	const errors: Record<string, string> = {}
	if (!input.label.trim()) {
		errors.label = input.t('Label is required.')
		return errors
	}
	if (!input.selectedGateway) {
		return errors
	}
	if (input.hasProviderCatalog && !input.effectiveCatalogProviderId) {
		errors[input.providerSelectorFieldName] = input.t('Please select a channel/provider.')
		return errors
	}

	for (const field of input.fieldsToValidate) {
		if (input.isEditing && field.type === 'secret' && !input.config[field.field]?.trim()) {
			continue
		}
		if (!field.optional && !input.config[field.field]?.trim()) {
			errors[field.field] = input.t('{field} is required.', { field: field.prompt })
			continue
		}

		if (field.type !== 'integer') {
			continue
		}

		const value = (input.config[field.field] ?? '').trim()
		if (value === '') {
			continue
		}
		if (!/^-?\d+$/.test(value)) {
			errors[field.field] = input.t('{field} must be an integer.', { field: field.prompt })
			continue
		}

		const numericValue = Number.parseInt(value, 10)
		if (field.min !== undefined && numericValue < field.min) {
			errors[field.field] = input.t('{field} must be at least {min}.', {
				field: field.prompt,
				min: field.min,
			})
			continue
		}

		if (field.max !== undefined && numericValue > field.max) {
			errors[field.field] = input.t('{field} must be at most {max}.', {
				field: field.prompt,
				max: field.max,
			})
		}
	}

	return errors
}
