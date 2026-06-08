<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		v-if="effectiveAllowedActions.canView"
		class="admin-settings"
		:name="t('twofactor_gateway', 'Two-Factor Gateway')"
		:description="t('twofactor_gateway', 'Configure messaging gateways used to send two-factor authentication codes. Each gateway can have multiple named configurations (instances), which enables multi-tenant setups.')">

		<!-- Loading -->
		<div v-if="loading" class="admin-settings__loading">
			<!-- TRANSLATORS "\u00A0" keeps the ellipsis attached to the previous word and avoids awkward line breaks. -->
			<NcLoadingIcon :size="32" />
			<p>{{ t('twofactor_gateway', 'Loading gateway configurations\u00A0…') }}</p>
		</div>

		<!-- Error state -->
		<NcEmptyContent
			v-else-if="error"
			:name="t('twofactor_gateway', 'Failed to load gateways')"
			:description="error">
			<template #icon>
				<AlertCircleIcon :size="32" />
			</template>
			<template #action>
				<NcButton @click="loadGateways">
					{{ t('twofactor_gateway', 'Retry') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<div v-else class="admin-settings__content">
			<div class="admin-settings__actions">
				<NcButton v-if="effectiveAllowedActions.canCreateInstances" type="primary" @click="openCreate">
					{{ t('twofactor_gateway', 'Add provider configuration') }}
				</NcButton>
			</div>

			<p v-if="effectiveAllowedActions.canReorderInstances && allInstances.length > 1" class="admin-settings__hint">
				{{ t('twofactor_gateway', 'Drag and drop instances to define routing priority across providers. Higher items run first.') }}
			</p>

			<draggable
				v-if="orderedInstances.length > 0"
				v-model="orderedInstances"
				class="admin-settings__instances"
				tag="div"
				item-key="orderKey"
				handle=".drag-handle"
				ghost-class="admin-settings__drag-ghost"
				:disabled="savingOrder || !effectiveAllowedActions.canReorderInstances"
				@end="onInstancesReordered">
				<template #item="{ element: item }">
					<div class="admin-settings__instance-row">
						<button
							v-if="effectiveAllowedActions.canReorderInstances"
							class="drag-handle"
							type="button"
							:title="t('twofactor_gateway', 'Drag to reorder priority')"
							:aria-label="t('twofactor_gateway', 'Drag to reorder priority')"
							:disabled="savingOrder">
							<DragIcon :size="20" />
						</button>
						<GatewayInstanceCard
							:instance="item.instance"
							:fields="item.fields"
							:provider-name="item.providerName"
							:group-names="item.groupNames ?? []"
							:show-set-default-action="effectiveAllowedActions.canSetDefaultInstances"
							:show-test-action="effectiveAllowedActions.canTestInstances"
							:show-routing-action="item.showRoutingAction && effectiveAllowedActions.canManageRouting"
							:show-edit-action="effectiveAllowedActions.canEditInstances"
							:show-delete-action="effectiveAllowedActions.canDeleteInstances"
							@edit="openEditById(item.gatewayId, $event)"
							@routing="openRoutingById(item.gatewayId, $event)"
							@delete="confirmDelete(item)"
							@set-default="onSetDefault(item)"
							@test="openTest(item)" />
					</div>
				</template>
			</draggable>
		</div>

		<GatewayInstanceModal
			v-if="effectiveAllowedActions.canCreateInstances || effectiveAllowedActions.canEditInstances"
			:show="showModal"
			:gateways="gateways"
			:groups="groups"
			:gateway-id="editingGatewayId"
			:instance-id="editingInstanceId"
			:initial-label="editingLabel"
			:initial-config="editingConfig"
			@close="closeModal"
			@saved="onSaved" />

		<GatewayRoutingModal
			v-if="routingItem && effectiveAllowedActions.canManageRouting"
			:show="showRoutingModal"
			:label="routingItem.instance.label"
			:instance-id="routingItem.instance.id"
			:groups="groups"
			:initial-group-ids="routingItem.instance.groupIds"
			@close="closeRoutingModal"
			@saved="onRoutingSaved" />

		<GatewayTestModal
			v-if="showTestModal && effectiveAllowedActions.canTestInstances"
			:show="showTestModal"
			:gateway-id="testingGatewayId"
			:instance-id="testingInstanceId"
			:label="testingLabel"
			@close="closeTestModal" />

		<NcDialog
			v-if="effectiveAllowedActions.canDeleteInstances"
			:name="t('twofactor_gateway', 'Delete instance')"
			:open="showDeleteDialog"
			:message="deleteConfirmMessage"
			@update:open="showDeleteDialog = $event">
			<template #actions>
				<NcButton @click="showDeleteDialog = false">
					{{ t('twofactor_gateway', 'Cancel') }}
				</NcButton>
				<NcButton type="error" :disabled="deleting" @click="doDelete">
					<template #icon>
						<NcLoadingIcon v-if="deleting" :size="20" />
					</template>
					{{ deleteButtonLabel }}
				</NcButton>
			</template>
		</NcDialog>

	</NcSettingsSection>
</template>

<docs>

Full app-level container for the complete Two-Factor Gateway admin management experience.

Use this component when another app intentionally wants to embed the full gateway-management screen instead of orchestrating lower-level building blocks itself.

Named non-primitive frontend types used by this page — such as `GatewayInfo`, `GatewayGroup`, `FlatInstanceEntry`, `GatewayAdminInitialData` and `GatewayAdminAllowedActions` — are documented centrally in [`Shared frontend types`](#/Shared%20frontend%20types).

### Where this payload comes from

This component can receive its first payload from two places:

1. **Nextcloud initial state on first render**
	 - `lib/Settings/AdminSettings.php` injects backend data with `provideInitialState('admin-settings', ...)`
	 - `src/admin.ts` reads that value with `loadState(...)`
	 - `normalizeGatewayAdminSnapshot(...)` converts it into the prop shape consumed by `GatewayAdminSettings`

2. **Live admin API during reloads and refreshes**
	 - `GatewayAdminApi.listAdminScreen()` calls `GET /ocs/v2.php/apps/twofactor_gateway/admin/screen`
	 - the response is normalized into the same frontend shape used by this component

So, in practice, there are two closely related payload layers:

- **transport payload**: what comes from initial state or the `/admin/screen` endpoint
- **normalized component payload**: what `GatewayAdminSettings` actually receives in `initialData`

### What the component actually expects

If you are integrating this component, think in terms of the following top-level object:

```json
{
	"gateways": [
		{
			"id": "whatsapp",
			"name": "WhatsApp",
			"instructions": "Provider-specific help text",
			"allowMarkdown": false,
			"fields": [
				{ "field": "base_url", "prompt": "Base URL", "default": "", "optional": false }
			],
			"providerSelector": {
				"field": "provider",
				"prompt": "Provider",
				"default": "",
				"optional": false
			},
			"providerCatalog": [
				{
					"id": "whatsapp",
					"name": "WhatsApp Cloud",
					"fields": [
						{ "field": "token", "prompt": "Token", "default": "", "optional": false }
					]
				}
			],
			"instances": [
				{
					"id": "wa-1",
					"providerId": "whatsapp",
					"label": "Production",
					"default": true,
					"createdAt": "2026-06-07T12:00:00+00:00",
					"config": { "provider": "whatsapp", "token": "..." },
					"isComplete": true,
					"groupIds": ["admins"],
					"priority": 10
				}
			]
		}
	],
	"groups": [
		{ "id": "admins", "displayName": "Admins" }
	],
	"items": [
		{
			"orderKey": "whatsapp:wa-1",
			"gatewayId": "whatsapp",
			"providerName": "WhatsApp Cloud",
			"fields": [
				{ "field": "token", "prompt": "Token", "default": "", "optional": false }
			],
			"instance": {
				"id": "wa-1",
				"providerId": "whatsapp",
				"label": "Production",
				"default": true,
				"createdAt": "2026-06-07T12:00:00+00:00",
				"config": { "provider": "whatsapp", "token": "..." },
				"isComplete": true,
				"groupIds": ["admins"],
				"priority": 10
			},
			"groupNames": ["Admins"],
			"showRoutingAction": true
		}
	],
	"allowedActions": {
		"canView": true,
		"canCreateInstances": true,
		"canEditInstances": true,
		"canDeleteInstances": true,
		"canSetDefaultInstances": true,
		"canManageRouting": true,
		"canTestInstances": true,
		"canReorderInstances": true
	}
}
```

### What each top-level key means

- `gateways`: the raw gateway catalog plus configured instances.
- `groups`: the selectable Nextcloud groups that can be used for routing.
- `items`: the ready-to-render list used by this component. If you want to simplify the frontend, this is usually the most important key.
- `allowedActions`: optional UI action flags used to hide or show actions in the container.

### Where the structure is documented

- **HTTP / backend contract**
	- OpenAPI documents the transport endpoints:
		- `GET /ocs/v2.php/apps/twofactor_gateway/admin/gateways`
		- `GET /ocs/v2.php/apps/twofactor_gateway/admin/groups`
		- `GET /ocs/v2.php/apps/twofactor_gateway/admin/screen`
- **Frontend normalized contract**
	- this page documents what `GatewayAdminSettings` expects after normalization
	- `doc/Frontend Reusable Surface.md` gives the same overview in narrative form
- **Exact code definitions**
	- `src/lib/twofactor-gateway/types/gateway.ts`
	- `src/lib/twofactor-gateway/services/gatewayAdminSnapshot.ts`
	- `src/lib/twofactor-gateway/services/adminGatewayViewModel.ts`

### OpenAPI vs frontend docs

- OpenAPI is the right place for backend HTTP contracts such as `/admin/gateways`, `/admin/groups` and `/admin/screen`.
- The dedicated [`Shared frontend types`](#/Shared%20frontend%20types) page is the right place for frontend-only normalized contracts such as `FlatInstanceEntry` and the final `initialData` shape.
- In other words: OpenAPI documents what the server sends over HTTP; the styleguide/docs explain how reusable frontend components expect to consume that data.

The preview below runs against in-memory styleguide data so you can inspect the full flow without touching a real backend.

### Preview

```vue
<template>
	<div class="demo-shell">
		<p class="demo-note">
			This preview runs against a styleguide-only in-memory backend, so you can inspect loading,
			modal and reorder flows without touching a real Nextcloud instance.
		</p>
		<GatewayAdminSettings />
	</div>
</template>

<script>
import { GatewayAdminSettings } from '@lib/twofactor-gateway/components/adminSettings'
import { resetStyleguideDemoState } from '../styleguide/demoHelpers'

export default {
	components: {
		GatewayAdminSettings,
	},
	created: resetStyleguideDemoState,
}
</script>

<style scoped>
.demo-shell {
	display: grid;
	gap: 1rem;
}

.demo-note {
	margin: 0;
	padding: 0.9rem 1rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}
</style>
```

</docs>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircle.vue'
import DragIcon from 'vue-material-design-icons/Drag.vue'
import draggable from 'vuedraggable'
import { t } from '@nextcloud/l10n'
import {
	buildPriorityUpdates,
	findFlatInstanceEntry,
	mergeOrderKeys,
	orderInstances,
	resolveGatewayAdminAllowedActions,
	useGatewayAdminApi,
	type GatewayAdminAllowedActions,
	type GatewayAdminInitialData,
	type FlatInstanceEntry,
	type GatewayGroup,
	type GatewayInfo,
} from '@lib/twofactor-gateway'
import { GatewayInstanceCard } from '@lib/twofactor-gateway/components/gatewayInstanceCard'
import { GatewayInstanceModal } from '@lib/twofactor-gateway/components/gatewayInstanceModal'
import { GatewayRoutingModal } from '@lib/twofactor-gateway/components/gatewayRoutingModal'
import { GatewayTestModal } from '@lib/twofactor-gateway/components/gatewayTestModal'

/**
 * Full app-level container for managing gateway instances, routing priority and related modal workflows.
 *
 * External hosts should inject an actor-aware backend/API adapter and may further
	* narrow the visible UI through the `allowedActions` prop instead of leaking raw host role semantics
	* into this component.
 *
 * @displayName GatewayAdminSettings
 */
export default defineComponent({
	name: 'AdminSettings',
	components: {
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		NcSettingsSection,
		AlertCircleIcon,
		DragIcon,
		GatewayInstanceCard,
		GatewayInstanceModal,
		GatewayRoutingModal,
		GatewayTestModal,
		draggable,
	},

	props: {
		/**
		 * Optional normalized bootstrap payload for the first render.
		 * See [Shared frontend types](#/Shared%20frontend%20types) → `GatewayAdminInitialData`.
		 */
		initialData: {
			// App entrypoints can hydrate the first render from backend-provided initial state.
			// Subsequent mutations still use the live GatewayAdminApi and refresh from the backend.
			type: Object as PropType<GatewayAdminInitialData | null>,
			default: null,
		},
		/**
		 * Optional UI action overrides passed by the host integration.
		 * See [Shared frontend types](#/Shared%20frontend%20types) → `GatewayAdminAllowedActions`.
		 */
		allowedActions: {
			// Host apps should pass UI action visibility instead of raw role names,
			// user ids or group lists. The backend/API remains the source of truth for enforcement.
			type: Object as PropType<Partial<GatewayAdminAllowedActions> | null>,
			default: null,
		},
	},

	setup() {
		return {
			t,
			gatewayAdminApi: useGatewayAdminApi(),
		}
	},

	data() {
		return {
			loading: false,
			error: '',
			gateways: [] as GatewayInfo[],
			groups: [] as GatewayGroup[],
			items: [] as FlatInstanceEntry[],

			showModal: false,
			editingGatewayId: '',
			editingInstanceId: '',
			editingLabel: '',
			editingConfig: {} as Record<string, string>,
			showRoutingModal: false,
			routingItem: null as FlatInstanceEntry | null,
			orderKeys: [] as string[],
			savingOrder: false,

			showTestModal: false,
			testingGatewayId: '',
			testingInstanceId: '',
			testingLabel: '',

			showDeleteDialog: false,
			pendingDelete: null as FlatInstanceEntry | null,
			deleting: false,
		}
	},

	computed: {
		effectiveAllowedActions(): GatewayAdminAllowedActions {
			return resolveGatewayAdminAllowedActions({
				...(this.initialData?.allowedActions ?? {}),
				...(this.allowedActions ?? {}),
			})
		},

		allInstances(): FlatInstanceEntry[] {
			return this.items
		},

		orderedInstances: {
			get(): FlatInstanceEntry[] {
				return orderInstances(this.allInstances, this.orderKeys)
			},
			set(value: FlatInstanceEntry[]) {
				this.orderKeys = value.map((item) => item.orderKey)
			},
		},

		deleteConfirmMessage(): string {
			return t(
				'twofactor_gateway',
				'Are you sure you want to delete the instance "{label}"? This action cannot be undone.',
				{ label: this.pendingDelete?.instance.label ?? '' },
			)
		},

		deleteButtonLabel(): string {
			if (this.deleting) {
				// TRANSLATORS "\u00A0" keeps the ellipsis attached to the previous word and avoids awkward line breaks.
				return t('twofactor_gateway', 'Deleting\u00A0…')
			}

			return t('twofactor_gateway', 'Delete')
		},
	},

	watch: {
		allInstances: {
			handler(items: FlatInstanceEntry[]) {
				this.orderKeys = mergeOrderKeys(this.orderKeys, items)
			},
			immediate: true,
		},
	},

	async created() {
		if (!this.effectiveAllowedActions.canView) {
			return
		}

		if (this.initialData !== null) {
			this.applyInitialData(this.initialData)
			return
		}

		await this.loadGateways()
	},

	methods: {
		applyInitialData(initialData: GatewayAdminInitialData) {
			this.gateways = JSON.parse(JSON.stringify(initialData.gateways)) as GatewayInfo[]
			this.groups = JSON.parse(JSON.stringify(initialData.groups)) as GatewayGroup[]
			this.items = JSON.parse(JSON.stringify(initialData.items)) as FlatInstanceEntry[]
			this.error = ''
			this.loading = false
		},

		openEditById(gatewayId: string, instanceId: string) {
			if (!this.effectiveAllowedActions.canEditInstances) {
				return
			}

			const item = findFlatInstanceEntry(this.allInstances, gatewayId, instanceId)
			if (!item) {
				return
			}
			this.openEdit(item)
		},

		openCreate() {
			if (!this.effectiveAllowedActions.canCreateInstances) {
				return
			}

			this.editingGatewayId = ''
			this.editingInstanceId = ''
			this.editingLabel = ''
			this.editingConfig = {}
			this.showModal = true
		},

		openEdit(item: FlatInstanceEntry) {
			if (!this.effectiveAllowedActions.canEditInstances) {
				return
			}

			this.editingGatewayId = item.gatewayId
			this.editingInstanceId = item.instance.id
			this.editingLabel = item.instance.label
			const sanitizedConfig = { ...item.instance.config }
			for (const field of item.fields) {
				if (field.type === 'secret' && sanitizedConfig[field.field] !== undefined) {
					sanitizedConfig[field.field] = ''
				}
			}
			this.editingConfig = sanitizedConfig
			this.showModal = true
		},

		openRoutingById(gatewayId: string, instanceId: string) {
			if (!this.effectiveAllowedActions.canManageRouting) {
				return
			}

			const item = findFlatInstanceEntry(this.allInstances, gatewayId, instanceId)
			if (!item) {
				return
			}

			this.routingItem = item
			this.showRoutingModal = true
		},

		closeModal() {
			this.showModal = false
		},

		closeRoutingModal() {
			this.showRoutingModal = false
			this.routingItem = null
		},

		openTest(item: FlatInstanceEntry) {
			if (!this.effectiveAllowedActions.canTestInstances) {
				return
			}

			this.testingGatewayId = item.gatewayId
			this.testingInstanceId = item.instance.id
			this.testingLabel = item.instance.label
			this.showTestModal = true
		},

		closeTestModal() {
			this.showTestModal = false
			this.testingGatewayId = ''
			this.testingInstanceId = ''
			this.testingLabel = ''
		},

		confirmDelete(item: FlatInstanceEntry) {
			if (!this.effectiveAllowedActions.canDeleteInstances) {
				return
			}

			this.pendingDelete = item
			this.showDeleteDialog = true
		},

		async doDelete() {
			if (!this.effectiveAllowedActions.canDeleteInstances || !this.pendingDelete) {
				return
			}
			this.deleting = true
			try {
				await this.gatewayAdminApi.deleteInstance(this.pendingDelete.gatewayId, this.pendingDelete.instance.id)
				this.showDeleteDialog = false
				this.pendingDelete = null
				await this.loadGateways()
			} catch (err) {
				console.error('Failed to delete gateway instance', err)
			} finally {
				this.deleting = false
			}
		},

		async onSetDefault(item: FlatInstanceEntry) {
			if (!this.effectiveAllowedActions.canSetDefaultInstances) {
				return
			}

			try {
				await this.gatewayAdminApi.setDefaultInstance(item.gatewayId, item.instance.id)
				await this.loadGateways()
			} catch (err) {
				console.error('Failed to set default gateway instance', err)
			}
		},

		async onSaved(payload: { gatewayId: string; instanceId: string; label: string; config: Record<string, string>; groupIds?: string[] }) {
			if (payload.instanceId && !this.effectiveAllowedActions.canEditInstances) {
				return
			}

			if (!payload.instanceId && !this.effectiveAllowedActions.canCreateInstances) {
				return
			}

			try {
				if (payload.instanceId) {
					const existingItem = findFlatInstanceEntry(this.allInstances, payload.gatewayId, payload.instanceId)
					await this.gatewayAdminApi.updateInstance(
						payload.gatewayId,
						payload.instanceId,
						payload.label,
						payload.config,
						existingItem?.instance.groupIds ?? [],
						existingItem?.instance.priority ?? 0,
					)
				} else {
					await this.gatewayAdminApi.createInstance(payload.gatewayId, payload.label, payload.config, payload.groupIds ?? [], 0)
				}
				this.showModal = false
				await this.loadGateways()
			} catch (err) {
				console.error('Failed to save gateway instance', err)
			}
		},

		async onRoutingSaved(payload: { groupIds: string[] }) {
			if (!this.effectiveAllowedActions.canManageRouting || !this.routingItem) {
				return
			}

			try {
				await this.gatewayAdminApi.updateInstance(
					this.routingItem.gatewayId,
					this.routingItem.instance.id,
					this.routingItem.instance.label,
					this.routingItem.instance.config,
					payload.groupIds,
					this.routingItem.instance.priority,
				)
				this.closeRoutingModal()
				await this.loadGateways()
			} catch (err) {
				console.error('Failed to save gateway routing', err)
			}
		},

		async onInstancesReordered(evt: { oldIndex?: number; newIndex?: number }) {
			if (!this.effectiveAllowedActions.canReorderInstances || this.savingOrder || evt.oldIndex === undefined || evt.newIndex === undefined || evt.oldIndex === evt.newIndex) {
				return
			}

			const orderedItems = this.orderedInstances
			if (orderedItems.length <= 1) {
				return
			}

			this.savingOrder = true
			try {
				const updates = buildPriorityUpdates(orderedItems)

				for (const { item, priority } of updates) {
					await this.gatewayAdminApi.updateInstance(
						item.gatewayId,
						item.instance.id,
						item.instance.label,
						item.instance.config,
						item.instance.groupIds,
						priority,
					)
				}

				await this.loadGateways()
			} catch (err) {
				console.error('Failed to reorder routing priorities', err)
			} finally {
				this.savingOrder = false
			}
		},

		async loadGateways() {
			if (!this.effectiveAllowedActions.canView) {
				this.loading = false
				this.error = ''
				this.gateways = []
				this.groups = []
				this.items = []
				return
			}

			this.loading = true
			this.error = ''
			try {
				const screen = await this.gatewayAdminApi.listAdminScreen()
				this.gateways = screen.gateways
				this.groups = screen.groups
				this.items = screen.items
			} catch (err) {
				console.error('Failed to load gateways', err)
				this.error = t('twofactor_gateway', 'Could not load gateway list. Please check your connection and try again.')
			} finally {
				this.loading = false
			}
		},
	},
})
</script>

<style lang="scss" scoped>
.admin-settings__loading {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 1rem;
	padding: 3rem;
	color: var(--color-text-lighter);
}

.admin-settings__content {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}

.admin-settings__actions {
	display: flex;
	justify-content: flex-start;
}

.admin-settings__hint {
	color: var(--color-text-lighter);
	font-size: 0.9rem;
	margin: 0;
}

.admin-settings__instances {
	display: flex;
	flex-direction: column;
	gap: 1rem;
	width: 100%;
}

.admin-settings__instance-row {
	display: flex;
	align-items: stretch;
	gap: 0.35rem;
	width: 100%;
}

.admin-settings__drag-ghost {
	opacity: 0.6;
}

.drag-handle {
	cursor: grab;
	align-self: stretch;
	width: 2.25rem;
	min-width: 2.25rem;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 0.25rem 0;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-element);
	background: var(--color-main-background);
	color: var(--color-main-text);
	opacity: 1;
}

.drag-handle:hover:not(:disabled) {
	background: var(--color-background-hover);
}

.drag-handle:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.drag-handle:active {
	cursor: grabbing;
}

:deep(.admin-settings__instance-row > .gateway-instance-card) {
	flex: 1 1 auto;
	min-width: 0;
}
</style>
