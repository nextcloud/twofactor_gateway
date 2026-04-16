<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
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

			<div v-if="allInstances.length > 0" class="admin-settings__instances">
				<GatewayInstanceCard
					v-for="item in allInstances"
					:key="item.gatewayId + ':' + item.instance.id"
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
		</div>

		<GatewayInstanceModal
			:show="showModal"
			:gateways="gateways"
			:groups="groups"
			:gateway-id="editingGatewayId"
			:instance-id="editingInstanceId"
			:initial-label="editingLabel"
			:initial-config="editingConfig"
			:initial-group-ids="editingGroupIds"
			:initial-priority="editingPriority"
			@close="closeModal"
			@saved="onSaved" />

		<GatewayRoutingModal
			v-if="routingItem"
			:show="showRoutingModal"
			:label="routingItem.instance.label"
			:instance-id="routingItem.instance.id"
			:groups="groups"
			:initial-group-ids="routingItem.instance.groupIds"
			:initial-priority="routingItem.instance.priority"
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
		GatewayInstanceCard,
		GatewayInstanceModal,
		GatewayRoutingModal,
		GatewayTestModal,
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
			editingGroupIds: [] as string[],
			editingPriority: 0,
			showRoutingModal: false,
			routingItem: null as FlatInstanceEntry | null,

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
				const routingRelevantForGateway = gateway.instances.length > 1
				for (const instance of gateway.instances) {
					const selectedProviderId = gateway.providerSelector
						? instance.config[gateway.providerSelector.field]
						: undefined
					const selectedProvider = gateway.providerCatalog?.find((provider) => provider.id === selectedProviderId)
					rows.push({
						gatewayId: gateway.id,
						providerName: selectedProvider?.name ?? gateway.name,
						fields: selectedProvider?.fields ?? gateway.fields,
						instance,
						showRoutingAction: routingRelevantForGateway || instance.groupIds.length > 0 || instance.priority > 0,
					})
				}
			}
			return rows
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
			this.editingGroupIds = []
			this.editingPriority = 0
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
			this.editingGroupIds = [...item.instance.groupIds]
			this.editingPriority = item.instance.priority
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

		async onSaved(payload: { gatewayId: string; instanceId: string; label: string; config: Record<string, string>; groupIds: string[]; priority: number }) {
			try {
				if (payload.instanceId) {
					await updateInstance(payload.gatewayId, payload.instanceId, payload.label, payload.config, payload.groupIds, payload.priority)
				} else {
					await createInstance(payload.gatewayId, payload.label, payload.config, payload.groupIds, payload.priority)
				}
				this.showModal = false
				await this.loadGateways()
			} catch (err) {
				console.error('Failed to save gateway instance', err)
			}
		},

		async onRoutingSaved(payload: { groupIds: string[]; priority: number }) {
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
					payload.priority,
				)
				this.closeRoutingModal()
				await this.loadGateways()
			} catch (err) {
				console.error('Failed to save gateway routing', err)
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
.admin-settings {
	&__loading {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 1rem;
		padding: 3rem;
		color: var(--color-text-lighter);
	}

	&__content {
		display: flex;
		flex-direction: column;
		gap: 1rem;
	}

	&__actions {
		display: flex;
		justify-content: flex-start;
	}

	&__instances {
		display: flex;
		flex-direction: column;
		gap: 1rem;
	}
}
</style>
