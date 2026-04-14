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

			<!-- Gateway selector visible only when creating -->
			<div v-if="!instanceId" class="modal-field">
				<label for="gateway-select">{{ t('twofactor_gateway', 'Gateway') }}</label>
				<NcSelect
					id="gateway-select"
					v-model="selectedGatewayId"
					:options="gatewayOptions"
					:placeholder="t('twofactor_gateway', 'Select a gateway…')"
					label="label"
					track-by="value"
					@update:model-value="onGatewayChange" />
			</div>

			<div v-if="!instanceId && selectedGatewayId" class="modal-divider" />

			<!-- Label -->
			<div v-if="selectedGateway || instanceId" class="modal-field">
				<NcTextField
					v-model="form.label"
					:label="t('twofactor_gateway', 'Label')"
					:placeholder="t('twofactor_gateway', 'e.g. Production, Client A…')"
					:required="true"
					:error="!!errors.label"
					:helper-text="errors.label ?? ''" />
			</div>

			<!-- Dynamic gateway fields -->
			<template v-if="selectedGateway || instanceId">
				<div
					v-for="field in currentFields"
					:key="field.field"
					class="modal-field">
					<NcTextField
						v-model="form.config[field.field]"
						:label="field.prompt + (field.optional ? ' (' + t('twofactor_gateway', 'optional') + ')' : '')"
						:placeholder="field.default || ''"
						:required="!field.optional"
						:error="!!errors[field.field]"
						:helper-text="errors[field.field] ?? ''" />
				</div>
			</template>

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
					type="primary"
					:disabled="saving || !canSave"
					@click="save">
					<template #icon>
						<NcLoadingIcon v-if="saving" :size="20" />
					</template>
					{{ saving ? t('twofactor_gateway', 'Saving…') : t('twofactor_gateway', 'Save') }}
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
import { t } from '@nextcloud/l10n'
import type { FieldDefinition, GatewayInfo, GatewayInstance } from '../services/adminGatewayApi.ts'

export default defineComponent({
	name: 'GatewayInstanceModal',
	components: { NcButton, NcLoadingIcon, NcModal, NcSelect, NcTextField },

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
				? t('twofactor_gateway', 'Edit Gateway Instance')
				: t('twofactor_gateway', 'Add Gateway Instance')
		},

		gatewayOptions(): Array<{ label: string; value: string }> {
			return this.gateways.map((g) => ({ label: g.name, value: g.id }))
		},

		selectedGateway(): GatewayInfo | undefined {
			const id = this.gatewayId || this.selectedGatewayId
			return this.gateways.find((g) => g.id === id)
		},

		currentFields(): FieldDefinition[] {
			return this.selectedGateway?.fields ?? []
		},

		currentInstructions(): string {
			return this.selectedGateway?.instructions ?? ''
		},

		sanitizedInstructions(): string {
			return domPurify.sanitize(this.currentInstructions, { ADD_ATTR: ['target'] })
		},

		canSave(): boolean {
			if (!this.form.label.trim()) {
				return false
			}
			if (!this.selectedGateway) {
				return false
			}
			return true
		},
	},

	watch: {
		initialLabel(val: string) {
			this.form.label = val
		},
		initialConfig(val: Record<string, string>) {
			this.form.config = { ...val }
		},
		gatewayId(val: string) {
			this.selectedGatewayId = val
		},
	},

	methods: {
		onGatewayChange() {
			// Reset config when gateway changes while creating
			this.form.config = {}
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
			for (const field of this.currentFields) {
				if (!field.optional && !this.form.config[field.field]?.trim()) {
					this.errors[field.field] = t('twofactor_gateway', '{field} is required.', { field: field.prompt })
				}
			}
			return Object.keys(this.errors).length === 0
		},

		async save() {
			if (!this.validate()) {
				return
			}
			this.saving = true
			try {
				const gwId = this.gatewayId || this.selectedGatewayId
				this.$emit('saved', {
					gatewayId: gwId,
					instanceId: this.instanceId,
					label: this.form.label.trim(),
					config: { ...this.form.config },
				})
			} finally {
				this.saving = false
			}
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

	.modal-divider {
		border-top: 1px solid var(--color-border);
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
}
</style>
