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
			<NcLoadingIcon :size="32" />
			<p>{{ t('twofactor_gateway', 'Loading gateway configurations…') }}</p>
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
					@edit="openEdit(item)"
					@delete="confirmDelete(item)"
					@set-default="onSetDefault(item)"
					@test="openTest(item)" />
			</div>
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
					{{ deleting ? t('twofactor_gateway', 'Deleting…') : t('twofactor_gateway', 'Delete') }}
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
import GatewayTestModal from '../components/GatewayTestModal.vue'
import {
	createInstance,
	deleteInstance,
	listGateways,
	setDefaultInstance,
	type FieldDefinition,
	type GatewayInfo,
	type GatewayInstance,
	updateInstance,
} from '../services/adminGatewayApi.ts'

interface FlatInstanceEntry {
	gatewayId: string
	providerName: string
	fields: FieldDefinition[]
	instance: GatewayInstance
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

			showModal: false,
			editingGatewayId: '',
			editingInstanceId: '',
			editingLabel: '',
			editingConfig: {} as Record<string, string>,

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
				for (const instance of gateway.instances) {
					rows.push({
						gatewayId: gateway.id,
						providerName: gateway.name,
						fields: gateway.fields,
						instance,
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
	},

	async created() {
		await this.loadGateways()
	},

	methods: {
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
			this.editingConfig = { ...item.instance.config }
			this.showModal = true
		},

		closeModal() {
			this.showModal = false
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
					await updateInstance(payload.gatewayId, payload.instanceId, payload.label, payload.config)
				} else {
					await createInstance(payload.gatewayId, payload.label, payload.config)
				}
				this.showModal = false
				await this.loadGateways()
			} catch (err) {
				console.error('Failed to save gateway instance', err)
			}
		},

		async loadGateways() {
			this.loading = true
			this.error = ''
			try {
				this.gateways = await listGateways()
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
