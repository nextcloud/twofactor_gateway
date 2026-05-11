// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it } from 'vitest'
import {
	computeCatalogSelectionState,
	normalizeProviderCatalog,
	resolveCurrentFields,
	resolveEffectiveCatalogProviderId,
	resolveFieldsToValidate,
	resolveGatewayId,
	resolveVisibleFields,
	validateGatewayInstanceForm,
} from '../../services/gatewayInstanceModalModel.ts'
import type { GatewayInfo } from '../../services/adminGatewayTypes.ts'

const smsGateway: GatewayInfo = {
	id: 'sms',
	name: 'SMS',
	instructions: '',
	allowMarkdown: false,
	fields: [],
	providerSelector: { field: 'provider', prompt: 'Provider', default: '', optional: false },
	providerCatalog: [
		{ id: 'smsapi', name: 'SMSApi', fields: [{ field: 'token', prompt: 'Token', default: '', optional: false }] },
		{ id: 'smsapi', name: 'SMSApi Duplicate', fields: [{ field: 'token', prompt: 'Token', default: '', optional: false }] },
		{ id: 'twilio', name: 'Twilio', fields: [{ field: 'sid', prompt: 'SID', default: '', optional: false }] },
	],
	instances: [],
}

describe('gatewayInstanceModalModel', () => {
	it('resolves gateway id from direct gateway prop and selection fallback', () => {
		expect(resolveGatewayId({
			gatewayId: 'signal',
			selectedGatewayId: '',
			isEditing: false,
			gateways: [smsGateway],
			config: {},
			instanceId: '',
		})).toBe('signal')

		expect(resolveGatewayId({
			gatewayId: '',
			selectedGatewayId: 'sms',
			isEditing: false,
			gateways: [smsGateway],
			config: {},
			instanceId: '',
		})).toBe('sms')
	})

	it('infers gateway from provider hints in edit mode', () => {
		const resolved = resolveGatewayId({
			gatewayId: '',
			selectedGatewayId: '',
			isEditing: true,
			gateways: [smsGateway],
			config: { provider: 'twilio' },
			instanceId: 'twilio:abc123',
		})
		expect(resolved).toBe('sms')
	})

	it('normalizes provider catalog by unique id and visible label', () => {
		const catalog = normalizeProviderCatalog(smsGateway.providerCatalog)
		expect(catalog.map((item) => item.id)).toEqual(['smsapi', 'twilio'])
	})

	it('resolves effective catalog provider from selected, config, and prefix fallback', () => {
		const catalog = normalizeProviderCatalog(smsGateway.providerCatalog)

		expect(resolveEffectiveCatalogProviderId({
			hasProviderCatalog: true,
			catalog,
			selectedCatalogProviderId: 'twilio',
			config: {},
			providerSelectorFieldName: 'provider',
			instanceId: '',
			isEditing: false,
		})).toBe('twilio')

		expect(resolveEffectiveCatalogProviderId({
			hasProviderCatalog: true,
			catalog,
			selectedCatalogProviderId: '',
			config: { provider: 'smsapi' },
			providerSelectorFieldName: 'provider',
			instanceId: '',
			isEditing: false,
		})).toBe('smsapi')

		expect(resolveEffectiveCatalogProviderId({
			hasProviderCatalog: true,
			catalog,
			selectedCatalogProviderId: '',
			config: {},
			providerSelectorFieldName: 'provider',
			instanceId: 'twilio:legacy',
			isEditing: true,
		})).toBe('twilio')
	})

	it('computes catalog selection state and injects selector in single-provider case', () => {
		const singleProviderGateway: GatewayInfo = {
			...smsGateway,
			providerCatalog: [{ id: 'smsapi', name: 'SMSApi', fields: [] }],
		}
		const catalog = normalizeProviderCatalog(singleProviderGateway.providerCatalog)

		const state = computeCatalogSelectionState({
			selectedGateway: singleProviderGateway,
			catalog,
			config: {},
			instanceId: '',
		})

		expect(state.selectedCatalogProviderId).toBe('smsapi')
		expect(state.config.provider).toBe('smsapi')
	})

	it('resolves current and visible fields for wizard-first flow', () => {
		const catalog = normalizeProviderCatalog(smsGateway.providerCatalog)
		const currentFields = resolveCurrentFields({
			selectedGateway: smsGateway,
			isEditing: false,
			config: { provider: 'smsapi' },
			hasProviderCatalog: true,
			catalog,
			effectiveCatalogProviderId: 'smsapi',
		})

		expect(currentFields.map((field) => field.field)).toContain('token')
		const visible = resolveVisibleFields([
			{ field: 'base_url', prompt: 'Base URL', default: '', optional: false },
			{ field: 'token', prompt: 'Token', default: '', optional: false },
		], true)
		expect(visible.map((field) => field.field)).toEqual(['base_url'])
	})

	it('returns empty validation fields when wizard flow is active', () => {
		const fields = resolveFieldsToValidate([
			{ field: 'token', prompt: 'Token', default: '', optional: false },
		], true)
		expect(fields).toEqual([])
	})

	it('validates required and integer constraints', () => {
		const errors = validateGatewayInstanceForm({
			label: 'Prod',
			isEditing: false,
			selectedGateway: smsGateway,
			hasProviderCatalog: false,
			effectiveCatalogProviderId: '',
			providerSelectorFieldName: 'provider',
			fieldsToValidate: [
				{ field: 'interval', prompt: 'Interval', default: '', optional: false, type: 'integer', min: 10, max: 20 },
			],
			config: { interval: '5' },
			t: (text, params) => {
				if (!params) {
					return text
				}
				return Object.entries(params).reduce((acc, [key, value]) => acc.replace(`{${key}}`, String(value)), text)
			},
		})

		expect(errors.interval).toContain('at least 10')
	})
})
