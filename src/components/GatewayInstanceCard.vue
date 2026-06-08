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
					:variant="instance.isComplete ? 'success' : 'warning'"
					no-close>
					{{ instance.isComplete ? t('twofactor_gateway', 'Configured') : t('twofactor_gateway', 'Incomplete') }}
				</NcChip>
			</div>

			<div class="card-actions">
				<!-- Set as default (only when not already default) -->
				<!--
				  Requests promotion of the current instance as the default instance.
				  @event set-default
				  @property {string} instanceId Stable instance identifier.
				-->
				<NcButton
					v-if="showSetDefaultAction && !instance.default"
					type="tertiary"
					:title="t('twofactor_gateway', 'Set as default')"
					:aria-label="t('twofactor_gateway', 'Set as default')"
					@click="$emit('set-default', instance.id)">
					<template #icon>
						<StarOutlineIcon :size="20" />
					</template>
				</NcButton>
				<NcButton
					v-else-if="instance.default"
					class="card-action-default"
					type="tertiary"
					:title="t('twofactor_gateway', 'This is the default instance')"
					:aria-label="t('twofactor_gateway', 'This is the default instance')"
					@click.prevent>
					<template #icon>
						<StarIcon :size="20" />
					</template>
				</NcButton>

				<!-- Test -->
				<!--
				  Requests a test-send flow for the current instance.
				  @event test
				  @property {string} instanceId Stable instance identifier.
				-->
				<NcButton
					v-if="showTestAction"
					class="card-action-test"
					type="tertiary"
					:title="t('twofactor_gateway', 'Test this instance')"
					:aria-label="t('twofactor_gateway', 'Test this instance')"
					:disabled="!instance.isComplete"
					@click="$emit('test', instance.id)">
					<template #icon>
						<TestTubeIcon :size="20" />
					</template>
				</NcButton>

				<!--
				  Requests editing of group-based routing for the current instance.
				  @event routing
				  @property {string} instanceId Stable instance identifier.
				-->
				<NcButton
					v-if="showRoutingAction"
					type="tertiary"
					:title="t('twofactor_gateway', 'Routing')"
					:aria-label="t('twofactor_gateway', 'Routing')"
					@click="$emit('routing', instance.id)">
					<template #icon>
						<AccountMultipleIcon :size="20" />
					</template>
				</NcButton>

				<!-- Edit -->
				<!--
				  Requests edition of the current instance.
				  @event edit
				  @property {string} instanceId Stable instance identifier.
				-->
				<NcButton
					v-if="showEditAction"
					type="tertiary"
					:title="t('twofactor_gateway', 'Edit')"
					:aria-label="t('twofactor_gateway', 'Edit')"
					@click="$emit('edit', instance.id)">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
				</NcButton>

				<!-- Delete -->
				<!--
				  Requests deletion of the current instance.
				  @event delete
				  @property {string} instanceId Stable instance identifier.
				-->
				<NcButton
					v-if="showDeleteAction"
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

<docs>

Lowest-coupling presentational block in the stable frontend reuse surface.

`GatewayInstanceCard` receives normalized data through props and emits user intent back to the parent, which makes it the safest public UI block to reuse from another app.

When a host wants a more restrictive UI policy, it can hide individual actions through props instead of passing raw role semantics into the card.

Non-primitive prop types used here — especially `GatewayInstance`, `FieldDefinition` and `GatewayGroup` — are documented centrally in [`Shared frontend types`](#/Shared%20frontend%20types).

### Preview

```vue
<template>
	<div class="demo-shell">
		<GatewayInstanceCard
			:instance="instance"
			:fields="fields"
			:groups="groups"
			provider-name="Acme SMS"
			@edit="remember('edit', $event)"
			@delete="remember('delete', $event)"
			@routing="remember('routing', $event)"
			@set-default="remember('set-default', $event)"
			@test="remember('test', $event)" />
		<p class="demo-log">
			Last emitted event: <code>{{ lastEvent }}</code>
		</p>
	</div>
</template>

<script>
import { GatewayInstanceCard } from '@lib/twofactor-gateway/components/gatewayInstanceCard'
import { cloneGatewayById, cloneStyleguideGroups } from '../styleguide/mocks/data'

export default {
	components: {
		GatewayInstanceCard,
	},
	data() {
		const gateway = cloneGatewayById('acme_sms')
		return {
			fields: gateway.fields,
			groups: cloneStyleguideGroups(),
			instance: gateway.instances[0],
			lastEvent: 'none',
		}
	},
	methods: {
		remember(action, instanceId) {
			this.lastEvent = `${action}(${instanceId})`
		},
	},
}
</script>

<style scoped>
.demo-shell {
	display: grid;
	gap: 0.75rem;
}

.demo-log {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

code {
	padding: 0.15rem 0.35rem;
	border-radius: var(--border-radius-element);
	background: var(--color-background-dark);
}
</style>
```

</docs>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import StarIcon from 'vue-material-design-icons/Star.vue'
import StarOutlineIcon from 'vue-material-design-icons/StarOutline.vue'
import TestTubeIcon from 'vue-material-design-icons/TestTube.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import { t } from '@nextcloud/l10n'
import type {
	FieldDefinition,
	GatewayGroup,
	GatewayInstance,
} from '@lib/twofactor-gateway'

const MASKED_FIELDS = ['token', 'password', 'secret', 'api_key', 'key', 'pass', 'credential']

/**
 * Data-driven card that renders a normalized gateway instance and emits user actions to the parent.
 */
export default defineComponent({
	name: 'GatewayInstanceCard',
	components: { NcButton, NcChip, DeleteIcon, PencilIcon, StarIcon, StarOutlineIcon, TestTubeIcon, AccountMultipleIcon },

	props: {
		/**
		 * Normalized gateway instance to render.
		 * See [Shared frontend types](#/Shared%20frontend%20types) → `GatewayInstance`.
		 */
		instance: { type: Object as PropType<GatewayInstance>, required: true },
		/**
		 * Field schema used to label values and mask hidden or secret fields.
		 * See [Shared frontend types](#/Shared%20frontend%20types) → `FieldDefinition`.
		 */
		fields: { type: Array as PropType<FieldDefinition[]>, default: () => [] },
		/**
		 * Human-readable provider name badge shown near the instance label.
		 */
		providerName: { type: String, default: '' },
		/**
		 * Optional host/backend-resolved group names. When provided, the card does not need to resolve ids via `groups`.
		 */
		groupNames: { type: Array as PropType<string[]>, default: () => [] },
		/**
		 * Available routing groups used to resolve group ids into display names.
		 * See [Shared frontend types](#/Shared%20frontend%20types) → `GatewayGroup`.
		 */
		groups: { type: Array as PropType<GatewayGroup[]>, default: () => [] },
		/**
		 * Toggles visibility of the set-default action button for non-default instances.
		 */
		showSetDefaultAction: { type: Boolean, default: true },
		/**
		 * Toggles visibility of the test action button.
		 */
		showTestAction: { type: Boolean, default: true },
		/**
		 * Toggles visibility of the routing action when the parent does not want to expose group-based routing edits.
		 */
		showRoutingAction: { type: Boolean, default: true },
		/**
		 * Toggles visibility of the edit action button.
		 */
		showEditAction: { type: Boolean, default: true },
		/**
		 * Toggles visibility of the delete action button.
		 */
		showDeleteAction: { type: Boolean, default: true },
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
			const booleanFields = new Set(
				this.fields
					.filter((field) => String(field.type ?? '').toLowerCase() === 'boolean')
					.map((field) => field.field),
			)
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

				if (booleanFields.has(key)) {
					const normalizedValue = String(value ?? '').trim().toLowerCase()
					if (['1', 'true', 'yes', 'on'].includes(normalizedValue)) {
						result[key] = t('twofactor_gateway', 'Enabled')
						continue
					}

					if (['0', 'false', 'no', 'off', ''].includes(normalizedValue)) {
						result[key] = t('twofactor_gateway', 'Disabled')
						continue
					}
				}

				const isSensitive = secretFields.has(key) || MASKED_FIELDS.some((f) => key.toLowerCase().includes(f))
				result[key] = isSensitive && value ? '••••••••' : (value || '—')
			}
			return result
		},

		routingGroupNames(): string[] {
			if (Array.isArray(this.groupNames) && this.groupNames.length > 0) {
				return this.groupNames
					.filter((groupName, index, groupNames) => groupName !== '' && groupNames.indexOf(groupName) === index)
			}

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
	flex: 1 1 auto;
	min-width: 0;
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

			:deep(.card-action-test) {
				color: var(--twofactor-gateway-test-action-color, #0f7a2a);
			}

			:deep(.card-action-test:hover:not([disabled])) {
				color: var(--twofactor-gateway-test-action-color-hover, #0b6122);
			}

			:deep(.card-action-test[disabled]) {
				color: var(--color-text-maxcontrast);
				opacity: 0.6;
			}

			:deep(.card-action-default) {
				color: #e0a400;
			}

			:deep(.card-action-default:hover) {
				color: #c58c00;
			}
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

:global(body) {
	--twofactor-gateway-test-action-color: #0f7a2a;
	--twofactor-gateway-test-action-color-hover: #0b6122;
}

:global(body[data-theme-dark]) .gateway-instance-card,
:global(body[data-themes='dark']) .gateway-instance-card,
:global(body[data-themes='dark-highcontrast']) .gateway-instance-card,
:global(.theme--dark) .gateway-instance-card {
	--twofactor-gateway-test-action-color: #75f59d;
	--twofactor-gateway-test-action-color-hover: #9bffc0;
}

@media (prefers-color-scheme: dark) {
	:global(body) {
		--twofactor-gateway-test-action-color: #75f59d;
		--twofactor-gateway-test-action-color-hover: #9bffc0;
	}
}
</style>
