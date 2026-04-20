<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
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
				<NcButton type="primary" @click="openCreate">
					{{ t('twofactor_gateway', 'Add provider configuration') }}
				</NcButton>
			</div>

			<p v-if="allInstances.length > 1" class="admin-settings__hint">
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
				:disabled="savingOrder"
				@end="onInstancesReordered">
				<template #item="{ element: item }">
					<div class="admin-settings__instance-row">
						<button
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
							:groups="groups"
							:show-routing-action="item.showRoutingAction"
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
			:show="showModal"
			:gateways="gateways"
			:gateway-id="editingGatewayId"
			:instance-id="editingInstanceId"
			:initial-label="editingLabel"
			:initial-config="editingConfig"
			@close="closeModal"
			@saved="onSaved" />

		<GatewayRoutingModal
			v-if="routingItem"
			:show="showRoutingModal"
			:label="routingItem.instance.label"
			:instance-id="routingItem.instance.id"
			:groups="groups"
			:initial-group-ids="routingItem.instance.groupIds"
			@close="closeRoutingModal"
			@saved="onRoutingSaved" />

		<GatewayTestModal
			v-if="showTestModal"
			:show="showTestModal"
			:gateway-id="testingGatewayId"
			:instance-id="testingInstanceId"
			:label="testingLabel"
			@close="closeTestModal" />

		<NcDialog
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

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircle.vue'
import DragIcon from 'vue-material-design-icons/Drag.vue'
import draggable from 'vuedraggable'
import { t } from '@nextcloud/l10n'
import GatewayInstanceCard from '../components/GatewayInstanceCard.vue'
import GatewayInstanceModal from '../components/GatewayInstanceModal.vue'
import GatewayRoutingModal from '../components/GatewayRoutingModal.vue'
import GatewayTestModal from '../components/GatewayTestModal.vue'
import {
	createInstance,
	deleteInstance,
	listGateways,
	listGroups,
	setDefaultInstance,
	type FieldDefinition,
	type GatewayGroup,
	type GatewayInfo,
	type GatewayInstance,
	updateInstance,
} from '../services/adminGatewayApi.ts'

interface FlatInstanceEntry {
	orderKey: string
	gatewayId: string
	providerName: string
	fields: FieldDefinition[]
	instance: GatewayInstance
	showRoutingAction: boolean
}

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

	setup() {
		return { t }
	},

	data() {
		return {
			loading: false,
			error: '',
			gateways: [] as GatewayInfo[],
			groups: [] as GatewayGroup[],

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
		allInstances(): FlatInstanceEntry[] {
			const rows: FlatInstanceEntry[] = []
			for (const gateway of this.gateways) {
				const instances = Array.isArray(gateway.instances) ? gateway.instances : []
				for (const instance of instances) {
					const groupIds = Array.isArray(instance.groupIds) ? instance.groupIds : []
					const priority = typeof instance.priority === 'number' ? instance.priority : 0
					const selectedProviderId = gateway.providerSelector
						? instance.config[gateway.providerSelector.field]
						: undefined
					const selectedProvider = gateway.providerCatalog?.find((provider) => provider.id === selectedProviderId)
					rows.push({
						orderKey: `${gateway.id}:${instance.id}`,
						gatewayId: gateway.id,
						providerName: selectedProvider?.name ?? gateway.name,
						fields: selectedProvider?.fields ?? gateway.fields,
						instance: {
							...instance,
							groupIds,
							priority,
						},
						showRoutingAction: true,
					})
				}
			}

			// Default visual order follows effective routing priority (higher first)
			// so cards don't appear randomly mixed before any drag interaction.
			return rows.sort((left, right) => {
				const priorityDiff = (right.instance.priority ?? 0) - (left.instance.priority ?? 0)
				if (priorityDiff !== 0) {
					return priorityDiff
				}

				const labelDiff = left.instance.label.localeCompare(right.instance.label)
				if (labelDiff !== 0) {
					return labelDiff
				}

				return left.orderKey.localeCompare(right.orderKey)
			})
		},

		orderedInstances: {
			get(): FlatInstanceEntry[] {
				const fallbackOrder = this.allInstances.map((item) => item.orderKey)
				const knownOrder = this.orderKeys.length > 0 ? this.orderKeys : fallbackOrder
				const position = new Map(knownOrder.map((key, index) => [key, index]))
				return [...this.allInstances].sort((left, right) => {
					const leftIndex = position.get(left.orderKey) ?? Number.MAX_SAFE_INTEGER
					const rightIndex = position.get(right.orderKey) ?? Number.MAX_SAFE_INTEGER
					return leftIndex - rightIndex
				})
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
				const nextKeys = items.map((item) => item.orderKey)
				const nextKeysSet = new Set(nextKeys)
				const keptKeys = this.orderKeys.filter((key) => nextKeysSet.has(key))
				const appendedKeys = nextKeys.filter((key) => !keptKeys.includes(key))
				this.orderKeys = [...keptKeys, ...appendedKeys]
			},
			immediate: true,
		},
	},

	async created() {
		await this.loadGateways()
	},

	methods: {
		openEditById(gatewayId: string, instanceId: string) {
			const item = this.allInstances.find((entry) => entry.gatewayId === gatewayId && entry.instance.id === instanceId)
				?? this.allInstances.find((entry) => entry.instance.id === instanceId)
			if (!item) {
				return
			}
			this.openEdit(item)
		},

		openCreate() {
			this.editingGatewayId = ''
			this.editingInstanceId = ''
			this.editingLabel = ''
			this.editingConfig = {}
			this.showModal = true
		},

		openEdit(item: FlatInstanceEntry) {
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
			const item = this.allInstances.find((entry) => entry.gatewayId === gatewayId && entry.instance.id === instanceId)
				?? this.allInstances.find((entry) => entry.instance.id === instanceId)
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
			this.pendingDelete = item
			this.showDeleteDialog = true
		},

		async doDelete() {
			if (!this.pendingDelete) {
				return
			}
			this.deleting = true
			try {
				await deleteInstance(this.pendingDelete.gatewayId, this.pendingDelete.instance.id)
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
			try {
				await setDefaultInstance(item.gatewayId, item.instance.id)
				await this.loadGateways()
			} catch (err) {
				console.error('Failed to set default gateway instance', err)
			}
		},

		async onSaved(payload: { gatewayId: string; instanceId: string; label: string; config: Record<string, string> }) {
			try {
				if (payload.instanceId) {
					const existingItem = this.allInstances.find((item) => item.gatewayId === payload.gatewayId && item.instance.id === payload.instanceId)
						?? this.allInstances.find((item) => item.instance.id === payload.instanceId)
					await updateInstance(
						payload.gatewayId,
						payload.instanceId,
						payload.label,
						payload.config,
						existingItem?.instance.groupIds ?? [],
						existingItem?.instance.priority ?? 0,
					)
				} else {
					await createInstance(payload.gatewayId, payload.label, payload.config, [], 0)
				}
				this.showModal = false
				await this.loadGateways()
			} catch (err) {
				console.error('Failed to save gateway instance', err)
			}
		},

		async onRoutingSaved(payload: { groupIds: string[] }) {
			if (!this.routingItem) {
				return
			}

			try {
				await updateInstance(
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
			if (this.savingOrder || evt.oldIndex === undefined || evt.newIndex === undefined || evt.oldIndex === evt.newIndex) {
				return
			}

			const orderedItems = this.orderedInstances
			if (orderedItems.length <= 1) {
				return
			}

			this.savingOrder = true
			try {
				const updates = orderedItems
					.map((item, index) => ({
						item,
						priority: orderedItems.length - index,
					}))
					.filter(({ item, priority }) => item.instance.priority !== priority)

				for (const { item, priority } of updates) {
					await updateInstance(
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
			this.loading = true
			this.error = ''
			try {
				const [gateways, groups] = await Promise.all([listGateways(), listGroups()])
				this.gateways = gateways
				this.groups = groups
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
