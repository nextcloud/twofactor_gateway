<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal
		:name="t('twofactor_gateway', 'Test Gateway Instance')"
		:show="show"
		size="small"
		@close="$emit('close')">
		<div class="gateway-test-modal">
			<h2>{{ t('twofactor_gateway', 'Test Gateway') }}</h2>
			<p>
				{{ t('twofactor_gateway', 'Send a test message via "{label}" to verify it is properly configured.', { label }) }}
			</p>
			<NcTextField
				v-model="identifier"
				:label="t('twofactor_gateway', 'Recipient identifier')"
				:placeholder="identifierPlaceholder"
				:required="true" />

			<NcNoteCard
				v-if="result"
				:type="result.success ? 'success' : 'error'"
				class="test-result-note-card">
				<p class="test-result-note-card__message">{{ result.message }}</p>
			</NcNoteCard>

			<div v-if="result?.accountInfo?.account_name" class="test-account-info">
				<NcAvatar
					:display-name="result.accountInfo.account_name"
					:url="result.accountInfo.account_avatar_url || undefined"
					:size="44"
					:is-no-user="true" />
				<span class="test-account-name">{{ result.accountInfo.account_name }}</span>
			</div>

			<div class="modal-actions">
				<NcButton type="secondary" @click="$emit('close')">
					{{ t('twofactor_gateway', 'Close') }}
				</NcButton>
				<NcButton
					type="primary"
					:disabled="testing || !identifier.trim()"
					@click="runTest">
					<template #icon>
						<NcLoadingIcon v-if="testing" :size="20" />
					</template>
					{{ sendButtonLabel }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { t } from '@nextcloud/l10n'
import { testInstance } from '../services/adminGatewayApi.ts'

export default defineComponent({
	name: 'GatewayTestModal',
	components: { NcAvatar, NcButton, NcLoadingIcon, NcModal, NcNoteCard, NcTextField },

	props: {
		show: { type: Boolean, default: false },
		gatewayId: { type: String, required: true },
		instanceId: { type: String, required: true },
		label: { type: String, required: true },
	},

	emits: ['close'],

	setup() {
		return { t }
	},

	data() {
		return {
			identifier: '',
			testing: false,
			result: null as { success: boolean; message: string } | null,
		}
	},

	watch: {
		show(val: boolean) {
			if (val) {
				this.identifier = ''
				this.result = null
			}
		},
	},

	computed: {
		identifierPlaceholder(): string {
			// TRANSLATORS "\u00A0" keeps the ellipsis attached to the previous word and avoids awkward line breaks.
			return t('twofactor_gateway', 'e.g. phone number, username, chat ID\u00A0…')
		},

		sendButtonLabel(): string {
			if (this.testing) {
				// TRANSLATORS "\u00A0" keeps the ellipsis attached to the previous word and avoids awkward line breaks.
				return t('twofactor_gateway', 'Sending\u00A0…')
			}

			return t('twofactor_gateway', 'Send Test')
		},
	},

	methods: {
		async runTest() {
			if (!this.identifier.trim()) {
				return
			}
			this.testing = true
			this.result = null
			try {
				this.result = await testInstance(this.gatewayId, this.instanceId, this.identifier.trim())
			} catch (err: unknown) {
				const message = (err as { response?: { data?: { ocs?: { data?: { message?: string } } } } })
					?.response?.data?.ocs?.data?.message
					?? t('twofactor_gateway', 'An unexpected error occurred.')
				this.result = { success: false, message }
			} finally {
				this.testing = false
			}
		},
	},
})
</script>

<style lang="scss" scoped>
.gateway-test-modal {
	padding: 1.5rem;
	display: flex;
	flex-direction: column;
	gap: 1rem;

	h2 {
		margin-top: 0;
	}

	.test-result-note-card {
		margin: 0;

		:deep(.notecard__content) {
			overflow-wrap: anywhere;
			word-break: break-word;
		}

		.test-result-note-card__message {
			margin: 0;
			white-space: pre-wrap;
		}
	}

	.modal-actions {
		display: flex;
		justify-content: flex-end;
		gap: 0.5rem;
		margin-top: 0.5rem;
	}

	.test-account-info {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		padding: 0.5rem 0;
	}

	.test-account-name {
		font-weight: 500;
		font-size: 1rem;
	}
}
</style>
