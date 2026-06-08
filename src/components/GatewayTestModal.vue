<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<!--
	  Requests closing the test modal.
	  @event close
	-->
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
			<div class="gateway-test-input" @keydown.enter.prevent.stop="runTest">
				<NcTextField
					v-model="identifier"
					:label="t('twofactor_gateway', 'Recipient identifier')"
					:placeholder="identifierPlaceholder"
					:required="true" />
			</div>

			<NcNoteCard
				v-if="result"
				:type="result.success ? 'success' : 'error'"
				class="test-result-note-card">
				<p class="test-result-note-card__message">
					{{ result.message }}
				</p>
			</NcNoteCard>

			<div v-if="result?.accountInfo?.account_name" class="test-account-info">
				<img
					v-if="accountAvatarUrl && !avatarLoadFailed"
					class="test-account-avatar"
					:src="accountAvatarUrl"
					:alt="accountName"
					width="44"
					height="44"
					@error="onAvatarError">
				<span v-else class="test-account-avatar-fallback" aria-hidden="true">{{ accountInitials }}</span>
				<span class="test-account-name">{{ accountName }}</span>
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

<docs>

Instance test workflow modal for sending a verification message through the stable Two-Factor Gateway test endpoint.

This component is useful when the consumer intentionally delegates test-send behavior to Two-Factor Gateway instead of implementing its own transport-specific test flow.

### Preview

```vue
<template>
	<div class="demo-shell">
		<p class="demo-note">
			Use any identifier to simulate success. Include the word <code>fail</code> to preview the error state.
		</p>
		<button class="demo-open-button" type="button" @click="showModal = true">
			Reopen test preview
		</button>
		<GatewayTestModal
			:show="showModal"
			gateway-id="acme_sms"
			:instance-id="instance.id"
			:label="instance.label"
			@close="showModal = false" />
	</div>
</template>

<script>
import { GatewayTestModal } from '@lib/twofactor-gateway/components/gatewayTestModal'
import { createStyleguideGatewayInstanceDemo } from '../styleguide/demoHelpers'

export default {
	components: {
		GatewayTestModal,
	},
	data() {
		const { instance } = createStyleguideGatewayInstanceDemo('acme_sms')
		return {
			showModal: false,
			instance,
		}
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
</style>
```

</docs>

<script lang="ts">
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { t } from '@nextcloud/l10n'
import { useGatewayAdminApi, type TestResult } from '@lib/twofactor-gateway'

/**
 * Test workflow modal for sending a verification message through the stable admin test endpoint.
 */
export default defineComponent({
	name: 'GatewayTestModal',
	components: { NcButton, NcLoadingIcon, NcModal, NcNoteCard, NcTextField },

	props: {
		/**
		 * Controls whether the modal is visible.
		 */
		show: { type: Boolean, default: false },
		/**
		 * Stable gateway identifier used for the test request.
		 */
		gatewayId: { type: String, required: true },
		/**
		 * Stable instance identifier used for the test request.
		 */
		instanceId: { type: String, required: true },
		/**
		 * Human-readable label shown in the modal copy.
		 */
		label: { type: String, required: true },
	},

	emits: ['close'],

	setup() {
		return {
			t,
			gatewayAdminApi: useGatewayAdminApi(),
		}
	},

	data() {
		return {
			identifier: '',
			testing: false,
			avatarLoadFailed: false,
			result: null as TestResult | null,
		}
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

		accountName(): string {
			return this.result?.accountInfo?.account_name?.trim() || ''
		},

		accountAvatarUrl(): string {
			const url = this.result?.accountInfo?.account_avatar_url?.trim() || ''
			return this.isRenderableAvatarUrl(url) ? url : ''
		},

		accountInitials(): string {
			const accountName = this.result?.accountInfo?.account_name?.trim() || ''

			if (!accountName) {
				return '?'
			}

			const tokens = accountName
				.split(/\s+/)
				.filter(Boolean)

			const initials = tokens
				.slice(0, 2)
				.map((token) => token[0])
				.join('')
				.toUpperCase()

			return initials || '?'
		},
	},

	watch: {
		show(val: boolean) {
			if (val) {
				this.identifier = ''
				this.avatarLoadFailed = false
				this.result = null
			}
		},
	},

	methods: {
		async runTest() {
			const identifier = this.identifier.trim()
			if (!identifier) {
				return
			}
			this.identifier = identifier
			this.avatarLoadFailed = false
			this.testing = true
			this.result = null
			try {
				this.result = await this.gatewayAdminApi.testInstance(this.gatewayId, this.instanceId, identifier)
			} catch (err: unknown) {
				const message = (err as { response?: { data?: { ocs?: { data?: { message?: string } } } } })
					?.response?.data?.ocs?.data?.message
					?? t('twofactor_gateway', 'An unexpected error occurred.')
				this.result = { success: false, message }
			} finally {
				this.testing = false
			}
		},

		onAvatarError() {
			this.avatarLoadFailed = true
		},

		isRenderableAvatarUrl(url: string): boolean {
			if (!url) {
				return false
			}

			if (!url.startsWith('data:image/')) {
				return false
			}

			return this.hasCompleteDataUriImage(url)
		},

		hasCompleteDataUriImage(url: string): boolean {
			const matches = url.match(/^data:image\/([a-z0-9.+-]+);base64,([\s\S]+)$/i)
			if (!matches) {
				return false
			}

			const imageType = matches[1].toLowerCase()
			if (!['jpeg', 'jpg', 'png', 'webp'].includes(imageType)) {
				return false
			}

			let payload = matches[2].replace(/\s+/g, '')

			if (!payload) {
				return false
			}

			const paddingLength = payload.length % 4
			if (paddingLength !== 0) {
				payload += '='.repeat(4 - paddingLength)
			}

			try {
				const binary = atob(payload)
				if (binary.length < 4) {
					return false
				}

				if (imageType === 'jpeg' || imageType === 'jpg') {
					const startOk = binary.charCodeAt(0) === 0xFF && binary.charCodeAt(1) === 0xD8
					const endOk = binary.charCodeAt(binary.length - 2) === 0xFF && binary.charCodeAt(binary.length - 1) === 0xD9
					return startOk && endOk
				}

				if (imageType === 'png') {
					return (
						binary.charCodeAt(0) === 0x89
						&& binary.charCodeAt(1) === 0x50
						&& binary.charCodeAt(2) === 0x4E
						&& binary.charCodeAt(3) === 0x47
						&& binary.charCodeAt(binary.length - 8) === 0x49
						&& binary.charCodeAt(binary.length - 7) === 0x45
						&& binary.charCodeAt(binary.length - 6) === 0x4E
						&& binary.charCodeAt(binary.length - 5) === 0x44
						&& binary.charCodeAt(binary.length - 4) === 0xAE
						&& binary.charCodeAt(binary.length - 3) === 0x42
						&& binary.charCodeAt(binary.length - 2) === 0x60
						&& binary.charCodeAt(binary.length - 1) === 0x82
					)
				}

				if (imageType === 'webp') {
					return (
						binary.length >= 12
						&& binary.slice(0, 4) === 'RIFF'
						&& binary.slice(8, 12) === 'WEBP'
					)
				}

				return false
			} catch {
				return false
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

	.test-account-avatar,
	.test-account-avatar-fallback {
		width: 44px;
		height: 44px;
		border-radius: 50%;
		flex-shrink: 0;
	}

	.test-account-avatar {
		object-fit: contain;
		object-position: center;
		padding: 2px;
		box-sizing: border-box;
		background: var(--color-background-dark);
	}

	.test-account-avatar-fallback {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		font-size: 0.875rem;
		font-weight: 600;
		text-transform: uppercase;
		background: var(--color-background-dark);
		color: var(--color-main-text);
	}

	.test-account-name {
		font-weight: 500;
		font-size: 1rem;
	}

}
</style>
