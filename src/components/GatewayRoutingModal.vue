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
		:name="t('twofactor_gateway', 'Routing settings')"
		:show="show"
		size="small"
		@close="$emit('close')">
		<div class="gateway-routing-modal">
			<h2>{{ t('twofactor_gateway', 'Routing settings') }}</h2>
			<p class="routing-copy">
				{{ t('twofactor_gateway', 'Choose when this instance should be used.') }}
			</p>

			<div class="routing-meta-grid">
				<div class="routing-meta-item">
					<span class="routing-meta-label">{{ t('twofactor_gateway', 'Instance') }}</span>
					<strong class="routing-meta-value">{{ label || '-' }}</strong>
				</div>
				<div class="routing-meta-item">
					<span class="routing-meta-label">{{ t('twofactor_gateway', 'Reference') }}</span>
					<code class="routing-meta-value routing-meta-value--code">{{ instanceId || '-' }}</code>
				</div>
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
					:no-wrap="false"
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

<docs>

Focused modal for editing group-based routing scope with low coupling.

This component does not call the backend directly. It only emits the selected `groupIds`, which keeps persistence in the parent component.

The main non-primitive prop type used here is `GatewayGroup[]`; see [`Shared frontend types`](#/Shared%20frontend%20types) for the canonical explanation.

### Standard emitted payload

When the user saves, the modal emits:

```ts
{
	groupIds: string[]
}
```

The `groupIds` array is always sorted before emission so parent components receive a stable payload.

### Preview

```vue
<template>
	<div class="demo-shell">
		<p class="demo-note">
			Save the modal to see the sorted <code>groupIds</code> payload that parents receive.
		</p>
		<button class="demo-open-button" type="button" @click="showModal = true">
			Reopen routing preview
		</button>
		<GatewayRoutingModal
			:show="showModal"
			:label="instance.label"
			:instance-id="instance.id"
			:groups="groups"
			:initial-group-ids="instance.groupIds"
			@close="showModal = false"
			@saved="onSaved" />
		<div v-if="savedPayload" class="demo-result">
			<strong>Last emitted payload</strong>
			<pre>{{ serializedPayload }}</pre>
		</div>
	</div>
</template>

<script>
import { GatewayRoutingModal } from '@lib/twofactor-gateway/components/gatewayRoutingModal'
import { cloneGatewayById, cloneStyleguideGroups } from '../styleguide/mocks/data'

export default {
	components: {
		GatewayRoutingModal,
	},
	data() {
		const gateway = cloneGatewayById('acme_sms')
		return {
			showModal: false,
			groups: cloneStyleguideGroups(),
			instance: gateway.instances[0],
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

code {
	padding: 0.15rem 0.35rem;
	border-radius: var(--border-radius-element);
	background: var(--color-background-dark);
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
	overflow-wrap: anywhere;
	font-size: 0.85rem;
}
</style>
```

</docs>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { t } from '@nextcloud/l10n'
import type { GatewayGroup } from '@lib/twofactor-gateway'

/**
 * Focused routing modal for editing which groups can use a given instance.
 */
export default defineComponent({
	name: 'GatewayRoutingModal',
	components: {
		NcButton,
		NcLoadingIcon,
		NcModal,
		NcSelect,
	},

	props: {
		/**
		 * Controls whether the modal is visible.
		 */
		show: { type: Boolean, default: false },
		/**
		 * Human-readable instance label shown in the modal header.
		 */
		label: { type: String, default: '' },
		/**
		 * Stable instance identifier shown for operator reference.
		 */
		instanceId: { type: String, default: '' },
		/**
		 * Available groups that may be selected for the routing scope.
		 * See [Shared frontend types](#/Shared%20frontend%20types) → `GatewayGroup`.
		 */
		groups: { type: Array as PropType<GatewayGroup[]>, default: () => [] },
		/**
		 * Group ids that should start selected when the modal opens.
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
			selectedGroups: this.groups.filter((group) => this.initialGroupIds.includes(group.id)) as GatewayGroup[],
		}
	},

	watch: {
		initialGroupIds(value: string[]) {
			this.selectedGroups = this.groups.filter((group) => value.includes(group.id))
		},
		groups() {
			this.selectedGroups = this.groups.filter((group) => this.initialGroupIds.includes(group.id))
		},
	},

	methods: {
		async save() {
			this.saving = true
			try {
				/**
				 * Emits the selected group ids in sorted order so the parent can persist routing scope.
				 *
				 * @event saved
				 * @property {string[]} groupIds Sorted group ids selected for the instance.
				 */
				this.$emit('saved', {
					groupIds: this.selectedGroups.map((group) => group.id).sort(),
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

	.routing-meta-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
		gap: 0.75rem;
	}

	.routing-meta-item {
		display: flex;
		flex-direction: column;
		gap: 0.35rem;
		padding: 0.65rem 0.75rem;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
		background: var(--color-background-dark);
	}

	.routing-meta-label {
		font-weight: 600;
		color: var(--color-text-lighter);
		font-size: 0.82rem;
	}

	.routing-meta-value {
		color: var(--color-main-text);
		font-size: 0.95rem;
		line-height: 1.3;
		font-family: var(--font-face, sans-serif);
	}

	.routing-meta-value--code {
		font-family: var(--font-face-monospace, monospace);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
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
