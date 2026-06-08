<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<!--
	  Requests closing the modal without persisting changes.
	  @event close
	-->
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

			<div v-if="!isEditing && canRenderProviderFields && !wizardPanelActive && groups.length > 0" class="modal-field">
				<label for="instance-groups-select">{{ t('twofactor_gateway', 'Groups') }}</label>
				<!-- TRANSLATORS "\u00A0" keeps the ellipsis attached to the previous word and avoids awkward line breaks. -->
				<NcSelect
					input-id="instance-groups-select"
					v-model="selectedGroups"
					:options="groups"
					:placeholder="t('twofactor_gateway', 'Restrict to groups\u00A0…')"
					label="displayName"
					track-by="id"
					:no-wrap="false"
					:multiple="true"
					:keep-open="true"
					:deselect-from-dropdown="true"
					:close-on-select="false" />
				<small class="modal-help-text">
					{{ t('twofactor_gateway', 'Choose the initial group scope for this instance. Delegated admins must select at least one managed group.') }}
				</small>
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

<docs>

Create/edit workflow modal for stable gateway instance configuration.

This component emits a normalized save payload instead of persisting changes by itself. It is a good fit when the consumer can provide gateway catalog data and handle the final mutation in the parent app.

Non-primitive types used here — especially `GatewayInfo`, `GatewayGroup` and `FieldDefinition` — are documented centrally in [`Shared frontend types`](#/Shared%20frontend%20types).

### Standard emitted payload

When the user saves, the modal emits the following normalized payload shape:

```ts
{
	gatewayId: string
	instanceId: string
	label: string
	config: Record<string, string>
	groupIds?: string[]
}
```

- `instanceId` is the existing instance id while editing, or `''` when creating.
- `groupIds` is only emitted during creation, because routing edits are handled by `GatewayRoutingModal`.

### Preview

```vue
<template>
	<div class="demo-shell">
		<p class="demo-note">
			This preview uses a fixed demo gateway with text, secret, boolean and integer fields.
		</p>
		<button class="demo-open-button" type="button" @click="showModal = true">
			Reopen modal preview
		</button>
		<GatewayInstanceModal
			:show="showModal"
			:gateways="gateways"
			:groups="groups"
			gateway-id="acme_sms"
			:initial-config="initialConfig"
			:initial-group-ids="['support']"
			@close="showModal = false"
			@saved="onSaved" />
		<div v-if="savedPayload" class="demo-result">
			<strong>Last emitted payload</strong>
			<pre>{{ serializedPayload }}</pre>
		</div>
	</div>
</template>

<script>
import { GatewayInstanceModal } from '@lib/twofactor-gateway/components/gatewayInstanceModal'
import { cloneGatewayById, cloneStyleguideGroups } from '../styleguide/mocks/data'

export default {
	components: {
		GatewayInstanceModal,
	},
	data() {
		return {
			showModal: false,
			gateways: [cloneGatewayById('acme_sms')],
			groups: cloneStyleguideGroups(),
			initialConfig: {
				sender_id: 'Preview Sender',
				api_token: 'demo-token',
				sandbox_mode: '1',
				request_timeout: '20',
			},
			savedPayload: null,
		}
	},
	computed: {
		serializedPayload() {
			return JSON.stringify(this.savedPayload, null, 2)
		},
	},
	methods: {
		onSaved(payload) {
			this.savedPayload = payload
			this.showModal = false
		},
	},
}
</script>

<style scoped>
.demo-shell {
	display: grid;
	gap: 1rem;
}

.demo-note {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.demo-open-button {
	width: fit-content;
	padding: 0.65rem 0.9rem;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-element);
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
}

.demo-result {
	padding: 1rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

pre {
	margin: 0.75rem 0 0;
	white-space: pre-wrap;
	word-break: break-word;
	font-size: 0.85rem;
}
</style>
```

</docs>

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
import {
	canUseGuidedSetupPanel,
	computeCatalogSelectionState,
	normalizeProviderCatalog,
	resolveCurrentFields,
	resolveEffectiveCatalogProviderId,
	resolveFieldsToValidate,
	resolveGatewayId,
	resolveVisibleFields,
	validateGatewayInstanceForm,
	type FieldDefinition,
	type GatewayGroup,
	type GatewayInfo,
} from '@lib/twofactor-gateway'

/**
 * Create/edit modal for stable gateway instance configuration, including provider catalog selection and guided setup support.
 */
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
		/**
		 * Controls whether the modal is visible.
		 */
		show: { type: Boolean, default: false },
		/**
		 * Stable gateway catalog available for selection or editing.
		 * See [Shared frontend types](#/Shared%20frontend%20types) → `GatewayInfo`.
		 */
		gateways: { type: Array as PropType<GatewayInfo[]>, required: true },
		/**
		 * Selected gateway id when the parent wants to preselect or lock a gateway.
		 */
		gatewayId: { type: String, default: '' },
		/**
		 * Existing instance id when editing an already persisted gateway instance.
		 */
		instanceId: { type: String, default: '' },
		/**
		 * Initial label value shown in the form.
		 */
		initialLabel: { type: String, default: '' },
		/**
		 * Initial configuration payload used to prefill editable fields.
		 */
		initialConfig: { type: Object as PropType<Record<string, string>>, default: () => ({}) },
		/**
		 * Available groups for initial routing scope selection when creating an instance.
		 * See [Shared frontend types](#/Shared%20frontend%20types) → `GatewayGroup`.
		 */
		groups: { type: Array as PropType<GatewayGroup[]>, default: () => [] },
		/**
		 * Initial group ids selected by the parent for the instance.
		 */
		initialGroupIds: { type: Array as PropType<string[]>, default: () => [] },
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
			selectedGroups: this.groups.filter((group) => this.initialGroupIds.includes(group.id)) as GatewayGroup[],
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
			return resolveGatewayId({
				gatewayId: this.gatewayId,
				selectedGatewayId: this.selectedGatewayId,
				isEditing: this.isEditing,
				gateways: this.gateways,
				config: this.form.config,
				instanceId: this.instanceId,
			})
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
			return normalizeProviderCatalog(this.selectedGateway?.providerCatalog)
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
			return resolveEffectiveCatalogProviderId({
				hasProviderCatalog: this.hasProviderCatalog,
				catalog: this.normalizedProviderCatalog,
				selectedCatalogProviderId: this.selectedCatalogProviderId,
				config: this.form.config,
				providerSelectorFieldName: this.providerSelectorFieldName,
				instanceId: this.instanceId,
				isEditing: this.isEditing,
			})
		},

		currentFields(): FieldDefinition[] {
			return resolveCurrentFields({
				selectedGateway: this.selectedGateway,
				isEditing: this.isEditing,
				config: this.form.config,
				hasProviderCatalog: this.hasProviderCatalog,
				catalog: this.normalizedProviderCatalog,
				effectiveCatalogProviderId: this.effectiveCatalogProviderId,
			})
		},

		showWizardFirstFlow(): boolean {
			return !this.isEditing && this.gatewaySetupPanel !== null
		},

		visibleFields(): FieldDefinition[] {
			return resolveVisibleFields(this.currentFields, this.showWizardFirstFlow)
		},

		fieldsToValidate(): FieldDefinition[] {
			return resolveFieldsToValidate(this.currentFields, this.showWizardFirstFlow)
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

			if (!canUseGuidedSetupPanel(this.selectedProviderId, this.currentFields)) {
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
				this.wizardPanelActive = false
			}
		},
		initialGroupIds(value: string[]) {
			this.selectedGroups = this.groups.filter((group) => value.includes(group.id))
		},
		groups() {
			this.selectedGroups = this.groups.filter((group) => this.initialGroupIds.includes(group.id))
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
			const state = computeCatalogSelectionState({
				selectedGateway: this.selectedGateway,
				catalog: this.normalizedProviderCatalog,
				config: this.form.config,
				instanceId: this.instanceId,
			})
			this.selectedCatalogProviderId = state.selectedCatalogProviderId
			this.form.config = state.config
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
			this.errors = validateGatewayInstanceForm({
				label: this.form.label,
				isEditing: this.isEditing,
				selectedGateway: this.selectedGateway,
				hasProviderCatalog: this.hasProviderCatalog,
				effectiveCatalogProviderId: this.effectiveCatalogProviderId,
				providerSelectorFieldName: this.providerSelectorFieldName,
				fieldsToValidate: this.fieldsToValidate,
				config: this.form.config,
				t: (text, parameters) => t('twofactor_gateway', text, parameters),
			})
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
				const payload: {
					gatewayId: string
					instanceId: string
					label: string
					config: Record<string, string>
					groupIds?: string[]
				} = {
					gatewayId: gwId,
					instanceId: this.instanceId,
					label: this.form.label.trim(),
					config,
				}
				if (!this.isEditing) {
					payload.groupIds = this.selectedGroups.map((group) => group.id).sort()
				}
				/**
				 * Emits the normalized payload for the parent to persist.
				 *
				 * @event saved
				 * @property {string} gatewayId Stable gateway identifier.
				 * @property {string} instanceId Existing instance identifier or an empty string when creating.
				 * @property {string} label User-facing instance label.
				 * @property {Record<string, string>} config Normalized configuration payload.
				 * @property {string[]} [groupIds] Initial routing scope selected while creating the instance.
				 */
				this.$emit('saved', payload)
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
