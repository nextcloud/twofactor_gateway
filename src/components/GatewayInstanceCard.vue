<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="gateway-instance-card" :class="{ 'gateway-instance-card--default': instance.default }">
		<div class="card-header">
			<div class="card-title">
				<span class="card-label">{{ instance.label }}</span>
				<span v-if="instance.default" class="badge badge--default">
					{{ t('twofactor_gateway', 'Default') }}
				</span>
				<span class="badge" :class="instance.isComplete ? 'badge--complete' : 'badge--incomplete'">
					{{ instance.isComplete ? t('twofactor_gateway', 'Configured') : t('twofactor_gateway', 'Incomplete') }}
				</span>
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
			{{ t('twofactor_gateway', 'Created: {date}', { date: formatDate(instance.createdAt) }) }}
		</div>
	</div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import StarIcon from 'vue-material-design-icons/Star.vue'
import StarOutlineIcon from 'vue-material-design-icons/StarOutline.vue'
import TestTubeIcon from 'vue-material-design-icons/TestTube.vue'
import { t } from '@nextcloud/l10n'
import type { FieldDefinition, GatewayInstance } from '../services/adminGatewayApi.ts'

const MASKED_FIELDS = ['token', 'password', 'secret', 'api_key', 'key', 'pass', 'credential']

export default defineComponent({
	name: 'GatewayInstanceCard',
	components: { NcButton, DeleteIcon, PencilIcon, StarIcon, StarOutlineIcon, TestTubeIcon },

	props: {
		instance: { type: Object as PropType<GatewayInstance>, required: true },
		fields: { type: Array as PropType<FieldDefinition[]>, default: () => [] },
	},

	emits: ['edit', 'delete', 'set-default', 'test'],

	setup() {
		return { t }
	},

	computed: {
		maskedConfig(): Record<string, string> {
			const result: Record<string, string> = {}
			for (const [key, value] of Object.entries(this.instance.config)) {
				const isSensitive = MASKED_FIELDS.some((f) => key.toLowerCase().includes(f))
				result[key] = isSensitive && value ? '••••••••' : (value || '—')
			}
			return result
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

	.badge {
		font-size: 0.75rem;
		padding: 0.1rem 0.5rem;
		border-radius: 1rem;

		&--default {
			background: var(--color-primary-element-light, #d5e7ff);
			color: var(--color-primary-element, #0066cc);
		}

		&--complete {
			background: var(--color-success-bg, #d5f5d5);
			color: var(--color-success, #1a7c1a);
		}

		&--incomplete {
			background: var(--color-warning-bg, #fff3cd);
			color: var(--color-warning, #996600);
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
}
</style>
