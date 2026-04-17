<!--
	- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="gateway-instance-card" :class="{ 'gateway-instance-card--default': instance.default }">
		<div class="card-header">
			<div class="card-title">
				<span class="card-label">{{ instance.label }}</span>
				<NcChip
					v-if="providerName"
					variant="tertiary"
					no-close>
					{{ providerName }}
				</NcChip>
				<NcChip
					v-if="instance.default"
					variant="primary"
					no-close>
					{{ t('twofactor_gateway', 'Default') }}
				</NcChip>
				<NcChip
					:variant="instance.isComplete ? 'success' : 'warning'"
					no-close>
					{{ instance.isComplete ? t('twofactor_gateway', 'Configured') : t('twofactor_gateway', 'Incomplete') }}
				</NcChip>
			</div>

			<div class="card-actions">
				<!-- Set as default (only when not already default) -->
				<NcButton
					v-if="!instance.default"
					type="tertiary"
					:title="t('twofactor_gateway', 'Set as default')"
					:aria-label="t('twofactor_gateway', 'Set as default')"
					@click="$emit('set-default', instance.id)">
					<template #icon>
						<StarOutlineIcon :size="20" />
					</template>
				</NcButton>
				<NcButton
					v-else
					type="tertiary"
					:title="t('twofactor_gateway', 'This is the default instance')"
					:aria-label="t('twofactor_gateway', 'This is the default instance')"
					:disabled="true">
					<template #icon>
						<StarIcon :size="20" />
					</template>
				</NcButton>

				<!-- Test -->
				<NcButton
					type="tertiary"
					:title="t('twofactor_gateway', 'Test this instance')"
					:aria-label="t('twofactor_gateway', 'Test this instance')"
					:disabled="!instance.isComplete"
					@click="$emit('test', instance.id)">
					<template #icon>
						<TestTubeIcon :size="20" />
					</template>
				</NcButton>

				<NcButton
					v-if="showRoutingAction"
					type="tertiary"
					:title="t('twofactor_gateway', 'Routing')"
					:aria-label="t('twofactor_gateway', 'Routing')"
					@click="$emit('routing', instance.id)">
					<template #icon>
						<TuneIcon :size="20" />
					</template>
				</NcButton>

				<!-- Edit -->
				<NcButton
					type="tertiary"
					:title="t('twofactor_gateway', 'Edit')"
					:aria-label="t('twofactor_gateway', 'Edit')"
					@click="$emit('edit', instance.id)">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
				</NcButton>

				<!-- Delete -->
				<NcButton
					type="tertiary"
					:title="t('twofactor_gateway', 'Delete')"
					:aria-label="t('twofactor_gateway', 'Delete')"
					@click="$emit('delete', instance.id)">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
				</NcButton>
			</div>
		</div>

		<!-- Config fields preview -->
		<div class="card-config">
			<div
				v-for="(value, key) in maskedConfig"
				:key="key"
				class="config-row">
				<span class="config-key">{{ fieldLabel(key) }}</span>
				<span class="config-value">{{ value }}</span>
			</div>
		</div>

		<div class="card-meta">
			<div v-if="instance.priority > 0">
				{{ t('twofactor_gateway', 'Priority: {priority}', { priority: instance.priority }) }}
			</div>
			<div v-if="routingGroupNames.length > 0" class="routing-groups">
				<span class="routing-groups-label">{{ t('twofactor_gateway', 'Groups') }}</span>
				<div class="routing-groups-chips">
					<NcChip
						v-for="groupName in routingGroupNames"
						:key="groupName"
						class="routing-group-chip"
						variant="tertiary"
						no-close>
						{{ groupName }}
					</NcChip>
				</div>
			</div>
			<div>
				{{ t('twofactor_gateway', 'Created: {date}', { date: formatDate(instance.createdAt) }) }}
			</div>
		</div>
	</div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import StarIcon from 'vue-material-design-icons/Star.vue'
import StarOutlineIcon from 'vue-material-design-icons/StarOutline.vue'
import TestTubeIcon from 'vue-material-design-icons/TestTube.vue'
import TuneIcon from 'vue-material-design-icons/Tune.vue'
import { t } from '@nextcloud/l10n'
import type { FieldDefinition, GatewayGroup, GatewayInstance } from '../services/adminGatewayApi.ts'

const MASKED_FIELDS = ['token', 'password', 'secret', 'api_key', 'key', 'pass', 'credential']

export default defineComponent({
	name: 'GatewayInstanceCard',
	components: { NcButton, NcChip, DeleteIcon, PencilIcon, StarIcon, StarOutlineIcon, TestTubeIcon, TuneIcon },

	props: {
		instance: { type: Object as PropType<GatewayInstance>, required: true },
		fields: { type: Array as PropType<FieldDefinition[]>, default: () => [] },
		providerName: { type: String, default: '' },
		groups: { type: Array as PropType<GatewayGroup[]>, default: () => [] },
		showRoutingAction: { type: Boolean, default: true },
	},

	emits: ['edit', 'delete', 'set-default', 'test', 'routing'],

	setup() {
		return { t }
	},

	computed: {
		maskedConfig(): Record<string, string> {
			const result: Record<string, string> = {}
			const config = this.instance.config && typeof this.instance.config === 'object'
				? this.instance.config
				: {}
			const secretFields = new Set(
				this.fields
					.filter((field) => field.type === 'secret')
					.map((field) => field.field),
			)
			const hiddenFields = new Set(
				this.fields
					.filter((field) => field.hidden)
					.map((field) => field.field),
			)
			for (const [key, value] of Object.entries(config)) {
				if (key === 'provider' || hiddenFields.has(key)) {
					continue
				}
				const isSensitive = secretFields.has(key) || MASKED_FIELDS.some((f) => key.toLowerCase().includes(f))
				result[key] = isSensitive && value ? '••••••••' : (value || '—')
			}
			return result
		},

		routingGroupNames(): string[] {
			const groupIds = Array.isArray(this.instance.groupIds) ? this.instance.groupIds : []
			if (groupIds.length === 0) {
				return []
			}

			return groupIds
				.map((groupId) => this.groups.find((group) => group.id === groupId)?.displayName ?? groupId)
				.filter((groupName, index, groupNames) => groupName !== '' && groupNames.indexOf(groupName) === index)
		},
	},

	methods: {
		fieldLabel(fieldKey: string): string {
			const match = this.fields.find((f) => f.field === fieldKey)
			return match?.prompt ?? fieldKey
		},

		formatDate(iso: string): string {
			try {
				return new Date(iso).toLocaleDateString(undefined, {
					year: 'numeric', month: 'short', day: 'numeric',
				})
			} catch {
				return iso
			}
		},
	},
})
</script>

<style lang="scss" scoped>
.gateway-instance-card {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 1rem;
	background: var(--color-main-background);
	transition: box-shadow 0.2s;

	&--default {
		border-color: var(--color-primary-element);
	}

	&:hover {
		box-shadow: 0 2px 8px rgba(0 0 0 / 10%);
	}

	.card-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 0.5rem;

		.card-title {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			flex-wrap: wrap;

			.card-label {
				font-weight: 600;
				font-size: 1rem;
			}
		}

		.card-actions {
			display: flex;
			gap: 0.25rem;
		}
	}

	.card-config {
		margin-top: 0.75rem;
		display: grid;
		grid-template-columns: max-content 1fr;
		gap: 0.25rem 1rem;

		.config-key {
			color: var(--color-text-lighter);
			font-size: 0.85rem;
		}

		.config-value {
			font-family: var(--font-face, monospace);
			font-size: 0.85rem;
			word-break: break-all;
		}
	}

	.card-meta {
		margin-top: 0.75rem;
		font-size: 0.8rem;
		color: var(--color-text-lighter);
	}

	.routing-groups {
		display: flex;
		align-items: center;
		gap: 0.45rem;
		flex-wrap: wrap;
	}

	.routing-groups-label {
		font-weight: 600;
	}

	.routing-groups-chips {
		display: flex;
		gap: 0.35rem;
		flex-wrap: wrap;
	}
}
</style>
