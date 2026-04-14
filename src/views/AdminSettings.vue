<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="admin-settings">
		<h2>{{ t('twofactor_gateway', 'Two-Factor Gateway') }}</h2>
		<p class="admin-settings__description">
			{{ t('twofactor_gateway', 'Configure messaging gateways used to send two-factor authentication codes. Each gateway can have multiple named configurations (instances), which enables multi-tenant setups.') }}
		</p>

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

		<!-- Gateway list -->
		<div v-else class="admin-settings__gateways">
			<GatewaySection
				v-for="gateway in gateways"
				:key="gateway.id"
				:gateway="gateway"
				@updated="loadGateways" />
		</div>
	</div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircle.vue'
import { t } from '@nextcloud/l10n'
import GatewaySection from '../components/GatewaySection.vue'
import { listGateways, type GatewayInfo } from '../services/adminGatewayApi.ts'

export default defineComponent({
	name: 'AdminSettings',
	components: { NcButton, NcEmptyContent, NcLoadingIcon, AlertCircleIcon, GatewaySection },

	setup() {
		return { t }
	},

	data() {
		return {
			loading: false,
			error: '',
			gateways: [] as GatewayInfo[],
		}
	},

	async created() {
		await this.loadGateways()
	},

	methods: {
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
	max-width: 900px;

	h2 {
		margin-top: 0;
	}

	&__description {
		color: var(--color-text-lighter);
		margin-bottom: 1.5rem;
	}

	&__loading {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 1rem;
		padding: 3rem;
		color: var(--color-text-lighter);
	}

	&__gateways {
		display: flex;
		flex-direction: column;
		gap: 1rem;
	}
}
</style>
