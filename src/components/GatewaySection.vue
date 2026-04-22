<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="gateway-section">
		<!-- Header -->
		<div class="gateway-section__header" @click="expanded = !expanded">
			<div class="header-left">
				<NcButton
					type="tertiary"
					class="expand-btn"
					:aria-label="expanded ? t('twofactor_gateway', 'Collapse') : t('twofactor_gateway', 'Expand')">
					<template #icon>
						<ChevronDownIcon :size="20" :class="{ 'icon-rotated': !expanded }" />
					</template>
				</NcButton>
				<span class="gateway-name">{{ gateway.name }}</span>
				<span class="instance-count">
					{{ t('twofactor_gateway', '{n} instance', '{n} instances', gateway.instances.length, { n: gateway.instances.length }) }}
				</span>
			</div>

			<NcButton
				type="secondary"
				@click.stop="openCreate">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('twofactor_gateway', 'Add instance') }}
			</NcButton>
		</div>

		<!-- Instance list -->
		<div v-if="expanded" class="gateway-section__body">
			<div v-if="instances.length === 0" class="empty-state">
				<p>{{ t('twofactor_gateway', 'No instances configured yet. Add one to get started.') }}</p>
			</div>

			<div v-else class="instance-list">
				<GatewayInstanceCard
					v-for="instance in instances"
					:key="instance.id"
					:instance="instance"
					:fields="gateway.fields"
					@edit="openEdit(instance.id)"
					@delete="confirmDelete(instance.id)"
					@set-default="onSetDefault(instance.id)"
					@test="openTest(instance.id)" />
			</div>
		</div>

		<!-- Create / Edit modal -->
		<GatewayInstanceModal
			:show="showModal"
			:gateways="[gateway]"
			:gateway-id="gateway.id"
			:instance-id="editingInstanceId"
			:initial-label="editingLabel"
			:initial-config="editingConfig"
			@close="closeModal"
			@saved="onSaved" />

		<!-- Test modal -->
		<GatewayTestModal
			v-if="testingInstanceId"
			:show="showTestModal"
			:gateway-id="gateway.id"
			:instance-id="testingInstanceId"
			:label="testingLabel"
			@close="closeTestModal" />

		<!-- Delete confirmation -->
		<NcDialog
			:name="t('twofactor_gateway', 'Delete instance')"
			:open="showDeleteDialog"
			:message="deleteConfirmMessage"
			@update:open="showDeleteDialog = $event">
			<template #actions>
				<NcButton @click="showDeleteDialog = false">
					{{ t('twofactor_gateway', 'Cancel') }}
				</NcButton>
				<NcButton
					type="error"
					:disabled="deleting"
					@click="doDelete">
					<template #icon>
						<NcLoadingIcon v-if="deleting" :size="20" />
					</template>
					{{ deleteActionLabel() }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { t } from '@nextcloud/l10n'
import GatewayInstanceCard from './GatewayInstanceCard.vue'
import GatewayInstanceModal from './GatewayInstanceModal.vue'
import GatewayTestModal from './GatewayTestModal.vue'
import {
	createInstance,
	deleteInstance,
	setDefaultInstance,
	updateInstance,
	type GatewayInfo,
	type GatewayInstance,
} from '../services/adminGatewayApi.ts'

export default defineComponent({
	name: 'GatewaySection',
	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		ChevronDownIcon,
		GatewayInstanceCard,
		GatewayInstanceModal,
		GatewayTestModal,
		PlusIcon,
	},

	props: {
		gateway: { type: Object as PropType<GatewayInfo>, required: true },
	},

	emits: ['updated'],

	setup() {
		return { t }
	},

	data() {
		return {
			expanded: this.gateway.instances.length > 0,
			instances: [...this.gateway.instances] as GatewayInstance[],

			// Create / edit modal
			showModal: false,
			editingInstanceId: '',
			editingLabel: '',
			editingConfig: {} as Record<string, string>,

			// Test modal
			showTestModal: false,
			testingInstanceId: '',
			testingLabel: '',

			// Delete dialog
			showDeleteDialog: false,
			deletingInstanceId: '',
			deleting: false,
		}
	},

	computed: {
		deleteConfirmMessage(): string {
			const inst = this.instances.find((i) => i.id === this.deletingInstanceId)
			return t(
				'twofactor_gateway',
				'Are you sure you want to delete the instance "{label}"? This action cannot be undone.',
				{ label: inst?.label ?? '' },
			)
		},
	},

	watch: {
		'gateway.instances'(val: GatewayInstance[]) {
			this.instances = [...val]
		},
	},

	methods: {
		// ── modals ────────────────────────────────────────────────────────────

		openCreate() {
			this.expanded = true
			this.editingInstanceId = ''
			this.editingLabel = ''
			this.editingConfig = {}
			this.showModal = true
		},

		openEdit(instanceId: string) {
			const inst = this.instances.find((i) => i.id === instanceId)
			if (!inst) return
			this.editingInstanceId = instanceId
			this.editingLabel = inst.label
			this.editingConfig = { ...inst.config }
			this.showModal = true
		},

		closeModal() {
			this.showModal = false
		},

		openTest(instanceId: string) {
			const inst = this.instances.find((i) => i.id === instanceId)
			if (!inst) return
			this.testingInstanceId = instanceId
			this.testingLabel = inst.label
			this.showTestModal = true
		},

		closeTestModal() {
			this.showTestModal = false
			this.testingInstanceId = ''
		},

		deleteActionLabel(): string {
			if (this.deleting) {
				// TRANSLATORS: Keep U+00A0 before the ellipsis so it never wraps to a new line, per Nextcloud translation style guide.
				return t('twofactor_gateway', 'Deleting\u00A0…')
			}

			return t('twofactor_gateway', 'Delete')
		},

		// ── save (create or update) ───────────────────────────────────────────

		async onSaved(payload: { gatewayId: string; instanceId: string; label: string; config: Record<string, string> }) {
			try {
				let updated: GatewayInstance
				if (payload.instanceId) {
					updated = await updateInstance(payload.gatewayId, payload.instanceId, payload.label, payload.config)
					const idx = this.instances.findIndex((i) => i.id === payload.instanceId)
					if (idx >= 0) {
						this.instances[idx] = updated
					}
				} else {
					updated = await createInstance(payload.gatewayId, payload.label, payload.config)
					this.instances.push(updated)
				}
				this.showModal = false
				this.$emit('updated')
			} catch (err) {
				console.error('Failed to save gateway instance', err)
			}
		},

		// ── delete ────────────────────────────────────────────────────────────

		confirmDelete(instanceId: string) {
			this.deletingInstanceId = instanceId
			this.showDeleteDialog = true
		},

		async doDelete() {
			this.deleting = true
			try {
				await deleteInstance(this.gateway.id, this.deletingInstanceId)
				this.instances = this.instances.filter((i) => i.id !== this.deletingInstanceId)
				this.showDeleteDialog = false
				this.$emit('updated')
			} catch (err) {
				console.error('Failed to delete gateway instance', err)
			} finally {
				this.deleting = false
			}
		},

		// ── set default ───────────────────────────────────────────────────────

		async onSetDefault(instanceId: string) {
			try {
				await setDefaultInstance(this.gateway.id, instanceId)
				this.instances = this.instances.map((inst) => ({
					...inst,
					default: inst.id === instanceId,
				}))
				this.$emit('updated')
			} catch (err) {
				console.error('Failed to set default gateway instance', err)
			}
		},
	},
})
</script>

<style lang="scss" scoped>
.gateway-section {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	overflow: hidden;

	&__header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 0.75rem 1rem;
		background: var(--color-background-hover);
		cursor: pointer;
		user-select: none;
		gap: 0.5rem;

		&:hover {
			background: var(--color-background-dark);
		}

		.header-left {
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}

		.expand-btn {
			flex-shrink: 0;
		}

		.gateway-name {
			font-weight: 600;
			font-size: 1rem;
		}

		.instance-count {
			font-size: 0.85rem;
			color: var(--color-text-lighter);
		}
	}

	&__body {
		padding: 1rem;
	}

	.empty-state {
		text-align: center;
		padding: 2rem 1rem;
		color: var(--color-text-lighter);
	}

	.instance-list {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}
}

.icon-rotated {
	transform: rotate(-90deg);
	transition: transform 0.2s;
}
</style>
