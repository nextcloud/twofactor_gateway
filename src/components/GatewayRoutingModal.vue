<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal
		:name="t('twofactor_gateway', 'Routing settings')"
		:show="show"
		size="small"
		@close="$emit('close')">
		<div class="gateway-routing-modal">
			<h2>{{ t('twofactor_gateway', 'Routing settings') }}</h2>
			<p class="routing-copy">
				{{ t('twofactor_gateway', 'Choose when this instance should be used.') }}
			</p>

			<div class="modal-field modal-field--readonly">
				<NcTextField
					:model-value="label"
					:label="t('twofactor_gateway', 'Instance')"
					:readonly="true" />
			</div>

			<div class="modal-field modal-field--readonly">
				<NcTextField
					:model-value="instanceId"
					:label="t('twofactor_gateway', 'Reference')"
					:readonly="true" />
			</div>

			<div class="modal-field">
				<NcTextField
					:model-value="String(priority)"
					:label="t('twofactor_gateway', 'Priority')"
					:placeholder="t('twofactor_gateway', '0 = no preference, higher runs first')"
					:error="!!errors.priority"
					:helper-text="errors.priority ?? t('twofactor_gateway', 'Higher priority instances are tried first when multiple instances match.')"
					@update:modelValue="onPriorityChange" />
			</div>

			<div v-if="groups.length > 0" class="modal-field">
				<label for="routing-groups-select">{{ t('twofactor_gateway', 'Groups') }}</label>
				<NcSelect
					input-id="routing-groups-select"
					v-model="selectedGroups"
					:options="groups"
					:placeholder="t('twofactor_gateway', 'Restrict to groups\u00A0\u2026')"
					label="displayName"
					track-by="id"
					:multiple="true"
					:keep-open="true"
					:deselect-from-dropdown="true"
					:close-on-select="false" />
				<small class="modal-help-text">
					{{ t('twofactor_gateway', 'Only users in these groups can use this instance. Leave empty to allow normal fallback.') }}
				</small>
			</div>

			<div class="modal-actions">
				<NcButton @click="$emit('close')">
					{{ t('twofactor_gateway', 'Cancel') }}
				</NcButton>
				<NcButton
					type="primary"
					:disabled="saving"
					@click="save">
					<template #icon>
						<NcLoadingIcon v-if="saving" :size="20" />
					</template>
					{{ saving ? t('twofactor_gateway', 'Saving\u00A0…') : t('twofactor_gateway', 'Save routing') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { t } from '@nextcloud/l10n'
import type { GatewayGroup } from '../services/adminGatewayApi.ts'

export default defineComponent({
	name: 'GatewayRoutingModal',
	components: {
		NcButton,
		NcLoadingIcon,
		NcModal,
		NcSelect,
		NcTextField,
	},

	props: {
		show: { type: Boolean, default: false },
		label: { type: String, default: '' },
		instanceId: { type: String, default: '' },
		groups: { type: Array as PropType<GatewayGroup[]>, default: () => [] },
		initialGroupIds: { type: Array as PropType<string[]>, default: () => [] },
		initialPriority: { type: Number, default: 0 },
	},

	emits: ['close', 'saved'],

	setup() {
		return { t }
	},

	data() {
		return {
			saving: false,
			selectedGroups: this.groups.filter((group) => this.initialGroupIds.includes(group.id)) as GatewayGroup[],
			priority: this.initialPriority,
			errors: {} as Record<string, string>,
		}
	},

	watch: {
		initialGroupIds(value: string[]) {
			this.selectedGroups = this.groups.filter((group) => value.includes(group.id))
		},
		initialPriority(value: number) {
			this.priority = value
		},
		groups() {
			this.selectedGroups = this.groups.filter((group) => this.initialGroupIds.includes(group.id))
		},
	},

	methods: {
		onPriorityChange(value: unknown): void {
			const normalizedValue = String(value ?? '').trim()
			this.priority = normalizedValue === '' ? 0 : Number.parseInt(normalizedValue, 10)
		},

		validate(): boolean {
			this.errors = {}
			if (!Number.isInteger(this.priority) || Number.isNaN(this.priority)) {
				this.errors.priority = t('twofactor_gateway', 'Priority must be an integer.')
				return false
			}

			return true
		},

		async save() {
			if (!this.validate()) {
				return
			}

			this.saving = true
			try {
				this.$emit('saved', {
					groupIds: this.selectedGroups.map((group) => group.id).sort(),
					priority: this.priority,
				})
			} finally {
				this.saving = false
			}
		},
	},
})
</script>

<style lang="scss" scoped>
.gateway-routing-modal {
	padding: 1.5rem;
	display: flex;
	flex-direction: column;
	gap: 1rem;

	h2 {
		margin: 0;
	}

	.routing-copy {
		margin: 0;
		color: var(--color-text-maxcontrast);
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

	.modal-help-text {
		color: var(--color-text-lighter);
		font-size: 0.85rem;
	}

	.modal-actions {
		display: flex;
		justify-content: flex-end;
		gap: 0.75rem;
	}
}
</style>
