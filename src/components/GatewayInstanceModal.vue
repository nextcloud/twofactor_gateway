<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal
		:name="modalTitle"
		:show="show"
		size="normal"
		@close="$emit('close')">
		<div class="gateway-instance-modal">
			<h2>{{ modalTitle }}</h2>

			<!-- Gateway selector visible only when creating and wizard session is not active -->
			<div v-if="!instanceId && !wizardPanelActive" class="modal-field">
				<label for="gateway-select">{{ t('twofactor_gateway', 'Provider') }}</label>
				<!-- TRANSLATORS "\u00A0" keeps the ellipsis attached to the previous word and avoids awkward line breaks. -->
				<NcSelect
					id="gateway-select"
					v-model="selectedGatewayId"
					:options="gatewayOptions"
					:placeholder="t('twofactor_gateway', 'Select a provider\u00A0…')"
					label="label"
					track-by="value"
					:reduce="selectOptionValue"
					@update:model-value="onGatewayChange" />
			</div>

			<div v-if="!instanceId && selectedGatewayId && !wizardPanelActive" class="modal-divider" />

			<div v-if="!instanceId && selectedGateway && hasProviderCatalog && !hasSingleProviderCatalog && !wizardPanelActive" class="modal-field">
				<label for="provider-catalog-select">{{ providerSelectorLabel }}</label>
				<!-- TRANSLATORS "\u00A0" keeps the ellipsis attached to the previous word and avoids awkward line breaks. -->
				<NcSelect
					id="provider-catalog-select"
					v-model="selectedCatalogProviderId"
					:options="providerCatalogOptions"
					:placeholder="t('twofactor_gateway', 'Select an option\u00A0…')"
					label="label"
					track-by="value"
					:reduce="selectOptionValue"
					@update:model-value="onProviderCatalogChange" />
			</div>

			<!-- Label (hidden when wizard session is active to keep focus on wizard steps) -->
			<div v-if="canRenderProviderFields && !wizardPanelActive" class="modal-field">
				<!-- TRANSLATORS "\u00A0" keeps the ellipsis attached to the previous word and avoids awkward line breaks. -->
				<NcTextField
					v-model="form.label"
					:label="t('twofactor_gateway', 'Label')"
					:placeholder="t('twofactor_gateway', 'e.g. Production, Client A\u00A0…')"
					:required="true"
					:error="!!errors.label"
					:helper-text="errors.label ?? ''" />
			</div>

			<!-- Dynamic gateway fields (hidden in wizard-first create mode; collected inside the wizard panel) -->
			<template v-if="canRenderProviderFields && !showWizardFirstFlow">
				<div
					v-for="field in visibleFields"
					:key="field.field"
					class="modal-field">
					<template v-if="field.type === 'secret'">
						<NcPasswordField
							v-model="form.config[field.field]"
							:label="field.prompt + (field.optional ? ' (' + t('twofactor_gateway', 'optional') + ')' : '')"
							:placeholder="fieldPlaceholder(field)"
							:required="!field.optional"
							:error="!!errors[field.field]"
							:helper-text="errors[field.field] ?? ''" />
						<small v-if="!errors[field.field] && field.helper" class="modal-field-helper">{{ field.helper }}</small>
					</template>
					<template v-else-if="field.type === 'boolean'">
						<div class="modal-switch-field">
							<NcCheckboxRadioSwitch
								type="switch"
								:model-value="booleanFieldValue(field)"
								@update:modelValue="onBooleanFieldChange(field, $event)">
								{{ field.prompt + (field.optional ? ' (' + t('twofactor_gateway', 'optional') + ')' : '') }}
							</NcCheckboxRadioSwitch>
							<small v-if="errors[field.field]" class="modal-switch-error">{{ errors[field.field] }}</small>
							<small v-else-if="field.helper" class="modal-switch-helper">{{ field.helper }}</small>
						</div>
					</template>
					<template v-else-if="field.type === 'integer'">
						<NcTextField
							:model-value="integerFieldValue(field)"
							:label="field.prompt + (field.optional ? ' (' + t('twofactor_gateway', 'optional') + ')' : '')"
							:placeholder="fieldPlaceholder(field)"
							:required="!field.optional"
							:error="!!errors[field.field]"
							:helper-text="errors[field.field] ?? ''"
							@update:modelValue="onIntegerFieldChange(field, $event)" />
						<small v-if="!errors[field.field] && field.helper" class="modal-field-helper">{{ field.helper }}</small>
					</template>
					<template v-else>
						<NcTextField
							v-model="form.config[field.field]"
							:label="field.prompt + (field.optional ? ' (' + t('twofactor_gateway', 'optional') + ')' : '')"
							:placeholder="fieldPlaceholder(field)"
							:required="!field.optional"
							:error="!!errors[field.field]"
							:helper-text="errors[field.field] ?? ''" />
						<small v-if="!errors[field.field] && field.helper" class="modal-field-helper">{{ field.helper }}</small>
					</template>
				</div>
			</template>

			<component
				:is="gatewaySetupPanel"
				v-if="gatewaySetupPanel"
				:gateway-id="resolvedGatewayId"
				:provider-id="selectedProviderId"
				:config="form.config"
				:can-start="canStartGuidedSetup"
				@merge-config="mergeConfigFromSetup"
				@setup-completed="onGuidedSetupCompleted"
				@update:wizard-active="wizardPanelActive = $event" />

			<!-- Instructions -->
			<div v-if="currentInstructions" class="modal-instructions">
				<!-- eslint-disable-next-line vue/no-v-html -->
				<p v-html="sanitizedInstructions" />
			</div>

			<div class="modal-actions">
				<NcButton @click="$emit('close')">
					{{ t('twofactor_gateway', 'Cancel') }}
				</NcButton>
				<NcButton
					v-if="shouldShowSaveButton"
					type="primary"
					:disabled="saving || !canSave"
					@click="save">
					<template #icon>
						<NcLoadingIcon v-if="saving" :size="20" />
					</template>
					{{ saveButtonLabel }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import domPurify from 'dompurify'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { t } from '@nextcloud/l10n'
import { resolveGatewaySetupPanel } from './providers/registry'
import type { FieldDefinition, GatewayInfo } from '../services/adminGatewayApi.ts'

export default defineComponent({
	name: 'GatewayInstanceModal',
	components: {
		NcButton,
		NcLoadingIcon,
		NcModal,
		NcSelect,
		NcTextField,
		NcPasswordField,
		NcCheckboxRadioSwitch,
	},

	props: {
		show: { type: Boolean, default: false },
		gateways: { type: Array as PropType<GatewayInfo[]>, required: true },
		gatewayId: { type: String, default: '' },
		instanceId: { type: String, default: '' },
		initialLabel: { type: String, default: '' },
		initialConfig: { type: Object as PropType<Record<string, string>>, default: () => ({}) },
	},

	emits: ['close', 'saved'],

	setup() {
		return { t }
	},

	data() {
		return {
			saving: false,
			selectedGatewayId: this.gatewayId || '',
			selectedCatalogProviderId: '',
			wizardPanelActive: false,
			guidedSetupReadyToSave: false,
			form: {
				label: this.initialLabel,
				config: { ...this.initialConfig } as Record<string, string>,
			},
			errors: {} as Record<string, string>,
		}
	},

	computed: {
		isEditing(): boolean {
			return !!this.instanceId
		},

		modalTitle(): string {
			return this.isEditing
				? t('twofactor_gateway', 'Edit provider configuration')
				: t('twofactor_gateway', 'Add provider configuration')
		},

		gatewayOptions(): Array<{ label: string; value: string }> {
			return this.gateways.map((g) => ({ label: g.name, value: g.id }))
		},

		resolvedGatewayId(): string {
			if (this.gatewayId) {
				return this.gatewayId
			}
			if (this.selectedGatewayId) {
				return this.selectedGatewayId
			}

			if (!this.isEditing) {
				return ''
			}

			const hints: string[] = []
			const providerFromConfig = (this.form.config.provider ?? '').trim()
			if (providerFromConfig !== '') {
				hints.push(providerFromConfig)
			}

			if (this.instanceId.includes(':')) {
				const fromPrefix = this.instanceId.split(':', 1)[0].trim()
				if (fromPrefix !== '') {
					hints.push(fromPrefix)
				}
			}

			for (const hint of hints) {
				const direct = this.gateways.find((gateway) => gateway.id === hint)
				if (direct) {
					return direct.id
				}

				const parent = this.gateways.find((gateway) =>
					(gateway.providerCatalog ?? []).some((provider) => provider.id === hint),
				)
				if (parent) {
					return parent.id
				}
			}

			if (this.isEditing && this.gateways.length > 0) {
				return this.gateways[0].id
			}

			return ''
		},

		selectedGateway(): GatewayInfo | undefined {
			const id = this.resolvedGatewayId
			return this.gateways.find((g) => g.id === id)
		},

		selectedProviderId(): string {
			if (this.hasProviderCatalog) {
				return this.effectiveCatalogProviderId
			}

			return this.resolvedGatewayId
		},

		normalizedProviderCatalog(): Array<{ id: string; name: string; fields: FieldDefinition[] }> {
			const rawCatalog = this.selectedGateway?.providerCatalog ?? []
			const byId = new Map<string, { id: string; name: string; fields: FieldDefinition[] }>()
			const byLabel = new Set<string>()

			for (const provider of rawCatalog) {
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
		},

		hasProviderCatalog(): boolean {
			return this.normalizedProviderCatalog.length > 0
		},

		hasSingleProviderCatalog(): boolean {
			return this.normalizedProviderCatalog.length === 1
		},

		providerSelectorFieldName(): string {
			return this.selectedGateway?.providerSelector?.field ?? 'provider'
		},

		providerSelectorLabel(): string {
			return this.selectedGateway?.providerSelector?.prompt ?? t('twofactor_gateway', 'Channel/Provider')
		},

		providerCatalogOptions(): Array<{ label: string; value: string }> {
			return this.normalizedProviderCatalog.map((provider) => ({
				label: provider.name,
				value: provider.id,
			}))
		},

		effectiveCatalogProviderId(): string {
			if (!this.hasProviderCatalog) {
				return ''
			}

			const catalog = this.normalizedProviderCatalog
			const selected = this.selectedCatalogProviderId.trim()
			if (selected !== '') {
				return selected
			}

			const fromConfig = (this.form.config[this.providerSelectorFieldName] ?? '').trim()
			if (fromConfig !== '') {
				return fromConfig
			}

			if (this.instanceId.includes(':')) {
				const fromPrefix = this.instanceId.split(':', 1)[0].trim()
				if (fromPrefix !== '' && catalog.some((provider) => provider.id === fromPrefix)) {
					return fromPrefix
				}
			}

			if (this.isEditing && catalog.length === 1) {
				return catalog[0].id
			}

			if (this.isEditing && catalog.length > 0) {
				return catalog[0].id
			}

			if (catalog.length === 1) {
				return catalog[0].id
			}

			return ''
		},

		currentFields(): FieldDefinition[] {
			if (!this.selectedGateway && this.isEditing) {
				return Object.keys(this.form.config)
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

			if (!this.hasProviderCatalog) {
				return (this.selectedGateway?.fields ?? []).filter((field) => !field.hidden)
			}
			const provider = this.normalizedProviderCatalog.find((item) => item.id === this.effectiveCatalogProviderId)
			return (provider?.fields ?? []).filter((field) => !field.hidden)
		},

		showWizardFirstFlow(): boolean {
			return !this.isEditing && this.gatewaySetupPanel !== null
		},

		visibleFields(): FieldDefinition[] {
			if (!this.showWizardFirstFlow) {
				return this.currentFields
			}

			const wizardBootstrapFields = new Set(['base_url', 'username', 'password', 'device_name'])
			return this.currentFields.filter((field) => wizardBootstrapFields.has(field.field))
		},

		fieldsToValidate(): FieldDefinition[] {
			if (this.showWizardFirstFlow) {
				return []
			}

			return this.currentFields
		},

		currentInstructions(): string {
			return this.selectedGateway?.instructions ?? ''
		},

		sanitizedInstructions(): string {
			return domPurify.sanitize(this.currentInstructions, { ADD_ATTR: ['target'] })
		},

		canRenderProviderFields(): boolean {
			if (this.isEditing) {
				return true
			}

			if (!this.selectedGateway) {
				return false
			}
			if (!this.hasProviderCatalog) {
				return true
			}
			return this.effectiveCatalogProviderId !== ''
		},

		canSave(): boolean {
			if (!this.form.label.trim()) {
				return false
			}
			if (!this.selectedGateway) {
				return false
			}
			if (this.hasProviderCatalog && !this.effectiveCatalogProviderId) {
				return false
			}
			return true
		},

		saveButtonLabel(): string {
			if (this.saving) {
				// TRANSLATORS "\u00A0" keeps the ellipsis attached to the previous word and avoids awkward line breaks.
				return t('twofactor_gateway', 'Saving\u00A0…')
			}

			return t('twofactor_gateway', 'Save')
		},

		gatewaySetupPanel() {
			if (this.isEditing) {
				return null
			}

			if (!this.selectedProviderId) {
				return null
			}

			return resolveGatewaySetupPanel(this.selectedProviderId)
		},

		shouldShowSaveButton(): boolean {
			if (this.showWizardFirstFlow) {
				return this.guidedSetupReadyToSave && !this.wizardPanelActive
			}

			return true
		},

		canStartGuidedSetup(): boolean {
			return this.form.label.trim() !== ''
		},
	},

	watch: {
		show(val: boolean) {
			if (!val) {
				this.guidedSetupReadyToSave = false
			}
		},
		initialLabel(val: string) {
			this.form.label = val
		},
		initialConfig(val: Record<string, string>) {
			this.form.config = { ...val }
			this.syncCatalogProviderSelection()
		},
		gatewayId(val: string) {
			this.selectedGatewayId = val
			this.syncCatalogProviderSelection()
		},
		selectedGateway: {
			handler(gateway: GatewayInfo | undefined) {
				if (!gateway) {
					this.selectedCatalogProviderId = ''
					return
				}
				if ((gateway.providerCatalog?.length ?? 0) === 0) {
					this.selectedCatalogProviderId = ''
					return
				}
				this.syncCatalogProviderSelection()
			},
			immediate: true,
		},
	},

	methods: {
		selectOptionValue(option: { value: string }): string {
			return option.value
		},

		booleanFieldValue(field: FieldDefinition): boolean {
			const value = (this.form.config[field.field] ?? field.default ?? '').trim()
			return value === '1' || value.toLowerCase() === 'true'
		},

		integerFieldValue(field: FieldDefinition): string {
			const value = this.form.config[field.field]
			if (value !== undefined) {
				return String(value)
			}

			return field.default ?? ''
		},

		onBooleanFieldChange(field: FieldDefinition, value: unknown): void {
			this.form.config = {
				...this.form.config,
				[field.field]: value === true ? '1' : '0',
			}
		},

		onIntegerFieldChange(field: FieldDefinition, value: unknown): void {
			this.form.config = {
				...this.form.config,
				[field.field]: String(value ?? '').trim(),
			}
		},

		syncCatalogProviderSelection() {
			const gateway = this.selectedGateway
			const catalog = this.normalizedProviderCatalog
			if (!gateway || catalog.length === 0) {
				this.selectedCatalogProviderId = ''
				return
			}

			const selectorFieldName = gateway.providerSelector?.field ?? 'provider'
			if (catalog.length === 1) {
				const onlyProviderId = String(catalog[0]?.id ?? '')
				this.selectedCatalogProviderId = onlyProviderId
				this.form.config = {
					...this.form.config,
					[selectorFieldName]: onlyProviderId,
				}
				return
			}

			const fromConfig = (this.form.config[selectorFieldName] ?? '').trim()
			if (fromConfig !== '') {
				this.selectedCatalogProviderId = fromConfig
				return
			}

			// In edit mode catalog child instances are prefixed as "provider:instance".
			if (this.instanceId.includes(':')) {
				const prefix = this.instanceId.split(':', 1)[0].trim()
				if (prefix !== '' && catalog.some((provider) => provider.id === prefix)) {
					this.selectedCatalogProviderId = prefix
					this.form.config = {
						...this.form.config,
						[selectorFieldName]: prefix,
					}
					return
				}
			}

			this.selectedCatalogProviderId = ''
		},

		onGatewayChange() {
			// Reset config when gateway changes while creating
			this.selectedCatalogProviderId = ''
			this.guidedSetupReadyToSave = false
			this.form.config = {}
			this.errors = {}
		},

		onProviderCatalogChange() {
			const selectorFieldName = this.providerSelectorFieldName
			this.guidedSetupReadyToSave = false
			this.form.config = {
				[selectorFieldName]: this.selectedCatalogProviderId,
			}
			this.errors = {}
		},

		validate(): boolean {
			this.errors = {}
			if (!this.form.label.trim()) {
				this.errors.label = t('twofactor_gateway', 'Label is required.')
				return false
			}
			if (!this.selectedGateway) {
				return false
			}
			if (this.hasProviderCatalog && !this.effectiveCatalogProviderId) {
				this.errors[this.providerSelectorFieldName] = t('twofactor_gateway', 'Please select a channel/provider.')
				return false
			}
			for (const field of this.fieldsToValidate) {
				if (this.isEditing && field.type === 'secret' && !this.form.config[field.field]?.trim()) {
					continue
				}
				if (!field.optional && !this.form.config[field.field]?.trim()) {
					this.errors[field.field] = t('twofactor_gateway', '{field} is required.', { field: field.prompt })
					continue
				}

				if (field.type === 'integer') {
					const value = (this.form.config[field.field] ?? '').trim()
					if (value === '') {
						continue
					}

					if (!/^-?\d+$/.test(value)) {
						this.errors[field.field] = t('twofactor_gateway', '{field} must be an integer.', { field: field.prompt })
						continue
					}

					const numericValue = Number.parseInt(value, 10)
					if (field.min !== undefined && numericValue < field.min) {
						this.errors[field.field] = t('twofactor_gateway', '{field} must be at least {min}.', {
							field: field.prompt,
							min: field.min,
						})
						continue
					}

					if (field.max !== undefined && numericValue > field.max) {
						this.errors[field.field] = t('twofactor_gateway', '{field} must be at most {max}.', {
							field: field.prompt,
							max: field.max,
						})
					}
				}
			}
			return Object.keys(this.errors).length === 0
		},

		fieldPlaceholder(field: FieldDefinition): string {
			if (field.type === 'secret' && this.isEditing) {
				return t('twofactor_gateway', 'Leave empty to keep current value')
			}

			return field.default || ''
		},

		async save() {
			if (!this.validate()) {
				return
			}
			this.saving = true
			try {
				const gwId = this.resolvedGatewayId
				const config = { ...this.form.config }
				if (this.hasProviderCatalog) {
					config[this.providerSelectorFieldName] = this.effectiveCatalogProviderId
				}
				this.$emit('saved', {
					gatewayId: gwId,
					instanceId: this.instanceId,
					label: this.form.label.trim(),
					config,
				})
			} finally {
				this.saving = false
			}
		},

		mergeConfigFromSetup(configPatch: Record<string, string>) {
			this.form.config = {
				...this.form.config,
				...configPatch,
			}
		},

		onGuidedSetupCompleted(configPatch: Record<string, string>) {
			this.mergeConfigFromSetup(configPatch)
			this.guidedSetupReadyToSave = true
			this.save()
		},
	},
})
</script>

<style lang="scss" scoped>
.gateway-instance-modal {
	padding: 1.5rem;
	display: flex;
	flex-direction: column;
	gap: 1rem;

	h2 {
		margin-top: 0;
	}

	.modal-field {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;

		label {
			font-weight: 600;
		}
	}

	.modal-field--readonly :deep(input) {
		opacity: 0.8;
	}

	.modal-divider {
		border-top: 1px solid var(--color-border);
	}

	.modal-help-text {
		color: var(--color-text-lighter);
		font-size: 0.85rem;
	}

	.modal-field-helper {
		color: var(--color-text-lighter);
		font-size: 0.85rem;
		line-height: 1.4;
	}

	.modal-instructions {
		padding: 0.75rem;
		background: var(--color-background-dark);
		border-radius: var(--border-radius);
		font-size: 0.9rem;
	}

	.modal-actions {
		display: flex;
		justify-content: flex-end;
		gap: 0.5rem;
		margin-top: 0.5rem;
	}

	.modal-switch-field {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
	}

	.modal-switch-error {
		color: var(--color-error);
		font-size: 0.85rem;
	}

	.modal-switch-helper {
		color: var(--color-text-lighter);
		font-size: 0.85rem;
	}

	.modal-advanced-toggle {
		display: flex;
		justify-content: flex-start;
	}

	.modal-advanced-section {
		margin-top: 0.5rem;
	}

	.modal-advanced-fieldset {
		border: 1px solid var(--color-border-dark);
		border-radius: var(--border-radius);
		padding: 0.75rem;
		display: flex;
		flex-direction: column;
		gap: 0.75rem;

		legend {
			padding: 0 0.35rem;
			font-weight: 600;
		}
	}

	.modal-advanced-description {
		margin: 0;
		color: var(--color-text-lighter);
		font-size: 0.9rem;
	}
}
</style>
