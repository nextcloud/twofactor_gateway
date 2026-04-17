<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="wizardRoot" class="modal-wizard" tabindex="-1">
		<h3>{{ t('twofactor_gateway', 'Guided WhatsApp Setup') }}</h3>
		<NcNoteCard :type="wizardMessageType" class="wizard-note-card">
			<p class="wizard-note-card__message">{{ wizardMessage }}</p>
		</NcNoteCard>

		<NcNoteCard
			v-if="wizardMessageType === 'success' && wizardAccountName"
			type="success"
			class="wizard-account-card">
			<div class="wizard-account-summary">
				<NcAvatar
					:display-name="wizardAccountName"
					:url="wizardAccountAvatarUrl || undefined"
					:is-no-user="true"
					:size="36" />
				<div class="wizard-account-summary__text">
					<strong>{{ t('twofactor_gateway', 'Connected account') }}</strong>
					<span>{{ wizardAccountName }}</span>
				</div>
			</div>
		</NcNoteCard>

		<!-- Bootstrap fields: collected before the wizard session starts -->
		<template v-if="!wizardSessionId">
			<div class="modal-field">
				<NcTextField
					v-model="bootstrapBaseUrl"
					:label="t('twofactor_gateway', 'Base URL to your WhatsApp API endpoint:')"
					:placeholder="t('twofactor_gateway', 'https://your-gowhatsapp-instance')"
					:required="true" />
			</div>
			<div class="modal-field">
				<NcTextField
					v-model="bootstrapDeviceName"
					:label="t('twofactor_gateway', 'Device name shown in WhatsApp linked devices:') + ' (' + t('twofactor_gateway', 'optional') + ')'"
					:placeholder="t('twofactor_gateway', 'TwoFactor Gateway')" />
			</div>
			<div class="modal-field">
				<NcTextField
					v-model="bootstrapUsername"
					:label="t('twofactor_gateway', 'API Username:') + ' (' + t('twofactor_gateway', 'optional') + ')'" />
			</div>
			<div class="modal-field">
				<NcPasswordField
					v-model="bootstrapPassword"
					:label="t('twofactor_gateway', 'API Password:') + ' (' + t('twofactor_gateway', 'optional') + ')'" />
			</div>
		</template>

		<div v-if="wizardStep === 'device_choice'" class="modal-field">
			<label for="wizard-device-strategy">{{ t('twofactor_gateway', 'How do you want to continue?') }}</label>
			<select id="wizard-device-strategy" v-model="wizardDeviceStrategy">
				<option value="use_existing">{{ t('twofactor_gateway', 'Use existing device') }}</option>
				<option value="logout_all_create_new">{{ t('twofactor_gateway', 'Logout all and create new') }}</option>
				<option value="create_new_keep_existing">{{ t('twofactor_gateway', 'Create new and keep existing') }}</option>
			</select>

			<div v-if="wizardDevices.length > 0" class="modal-field">
				<label for="wizard-device-id">{{ t('twofactor_gateway', 'Device') }}</label>
				<select id="wizard-device-id" v-model="wizardDeviceId">
					<option
						v-for="device in wizardDevices"
						:key="device.id"
						:value="device.id">
						{{ device.display_name || device.phone_number || device.id }}
					</option>
				</select>
			</div>
		</div>

		<div v-if="wizardStep === 'phone'" class="modal-field">
			<NcTextField
				v-model="wizardPhone"
				:label="t('twofactor_gateway', 'Phone number')"
				:placeholder="t('twofactor_gateway', 'e.g. 5511999998888')"
				:required="true" />
		</div>

		<div v-if="wizardStep === 'already_logged_in'" class="wizard-actions-inline">
			<NcButton
				type="secondary"
				:disabled="wizardLoading"
				@click="runWizardStep('submit_phone', { phone: wizardPhone, continue_existing: true })">
				{{ t('twofactor_gateway', 'Continue existing session') }}
			</NcButton>
			<NcButton
				type="warning"
				:disabled="wizardLoading"
				@click="runWizardStep('submit_phone', { phone: wizardPhone, continue_existing: false })">
				{{ t('twofactor_gateway', 'Logout and relink') }}
			</NcButton>
		</div>

		<div v-if="wizardStep === 'pairing'" class="wizard-pairing-section">
			<div class="wizard-pairing-phone">
				<span class="wizard-pairing-phone-label">{{ t('twofactor_gateway', 'Phone number') }}:</span>
				<strong>+{{ wizardPhone }}</strong>
			</div>
			<div class="wizard-pairing-code">
				<span class="wizard-pairing-code-label">{{ t('twofactor_gateway', 'Pairing code') }}:</span>
				<strong class="wizard-pairing-code-value">{{ wizardPairCode }}</strong>
			</div>
			<div v-if="pairingPolling" class="wizard-pairing-polling">
				<span class="wizard-pairing-polling-label">{{ t('twofactor_gateway', 'Waiting for pairing confirmation…') }}</span>
				<NcProgressBar
					:value="Math.round((pairingAttempt / pairingMaxAttempts) * 100)"
					type="linear"
					size="small" />
			</div>
		</div>

		<div class="wizard-actions-inline">
			<NcButton
				v-if="!wizardSessionId"
				variant="secondary"
				:disabled="wizardLoading || !canStart || !bootstrapBaseUrl.trim()"
				@click="startWizard">
				{{ t('twofactor_gateway', 'Start guided setup') }}
			</NcButton>

			<NcButton
				v-if="wizardSessionId"
				variant="tertiary"
				:disabled="wizardLoading"
				@click="cancelWizard">
				{{ t('twofactor_gateway', 'Back') }}
			</NcButton>

			<NcButton
				v-if="wizardStep === 'device_choice'"
				variant="primary"
				:disabled="wizardLoading"
				@click="runWizardStep('choose_device', { strategy: wizardDeviceStrategy, device_id: wizardDeviceId })">
				{{ t('twofactor_gateway', 'Continue') }}
			</NcButton>

			<NcButton
				v-if="wizardStep === 'phone'"
				variant="primary"
				:disabled="wizardLoading"
				@click="runWizardStep('submit_phone', { phone: wizardPhone })">
				{{ t('twofactor_gateway', 'Request pairing code') }}
			</NcButton>
		</div>
	</div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { t } from '@nextcloud/l10n'
import {
	cancelInteractiveSetup,
	interactiveSetupStep,
	startInteractiveSetup,
	type InteractiveSetupResponse,
} from '../../../services/adminGatewayApi.ts'

type WizardDevice = {
	id: string
	display_name?: string
	phone_number?: string
	state?: string
}

export default defineComponent({
	name: 'SetupPanel',
	components: {
		NcAvatar,
		NcButton,
		NcNoteCard,
		NcPasswordField,
		NcProgressBar,
		NcTextField,
	},
	props: {
		gatewayId: { type: String, required: true },
		providerId: { type: String, required: true },
		config: { type: Object as PropType<Record<string, string>>, required: true },
		canStart: { type: Boolean, default: true },
	},
	emits: ['merge-config', 'setup-completed', 'update:wizardActive'],
	setup() {
		return { t }
	},
	data() {
		return {
			wizardLoading: false,
			wizardSessionId: '' as string,
			wizardStep: '',
			wizardMessage: t('twofactor_gateway', 'Use the guided setup to validate API, choose device strategy, and complete pairing.'),
			wizardMessageType: 'info' as 'info' | 'success' | 'warning' | 'error',
			wizardPairCode: '',
			wizardAccountName: '',
			wizardAccountAvatarUrl: '',
			wizardDeviceStrategy: 'use_existing',
			wizardDeviceId: '',
			wizardPhone: '',
			wizardDevices: [] as WizardDevice[],
			pairingPolling: false,
			pairingAttempt: 0,
			pairingMaxAttempts: 60,
			pairingPollTimer: null as ReturnType<typeof setTimeout> | null,
			bootstrapBaseUrl: this.config.base_url ?? '',
			bootstrapDeviceName: this.config.device_name ?? '',
			bootstrapUsername: this.config.username ?? '',
			bootstrapPassword: this.config.password ?? '',
		}
	},
	watch: {
		wizardSessionId(val: string) {
			this.$emit('update:wizardActive', val !== '')
		},
	},
	methods: {
		focusWizardRoot() {
			this.$nextTick(() => {
				const wizardRoot = this.$refs.wizardRoot as HTMLElement | undefined
				wizardRoot?.focus({ preventScroll: true })
			})
		},

		startPairingPolling() {
			this.stopPairingPolling()
			this.pairingAttempt = 0
			this.pairingPolling = true
			this.schedulePairingPoll()
		},

		stopPairingPolling() {
			if (this.pairingPollTimer !== null) {
				clearTimeout(this.pairingPollTimer)
				this.pairingPollTimer = null
			}
			this.pairingPolling = false
		},

		schedulePairingPoll() {
			if (this.pairingAttempt >= this.pairingMaxAttempts) {
				this.stopPairingPolling()
				this.wizardMessage = t('twofactor_gateway', 'Pairing confirmation timed out. Enter the code in WhatsApp and try again, or go Back to start over.')
				this.wizardMessageType = 'warning'
				return
			}
			this.pairingPollTimer = setTimeout(() => { this.pollPairingOnce() }, 3000)
		},

		async pollPairingOnce() {
			if (!this.wizardSessionId || this.wizardStep !== 'pairing') {
				this.stopPairingPolling()
				return
			}
			this.pairingAttempt++
			try {
				const response = await interactiveSetupStep(this.gatewayId, this.wizardSessionId, 'poll_pairing')
				this.applyWizardResponse(response)
				if (this.wizardStep === 'pairing' && response.status !== 'error') {
					this.schedulePairingPoll()
				} else {
					this.stopPairingPolling()
				}
			} catch (error) {
				this.stopPairingPolling()
				this.wizardMessage = this.normalizeErrorMessage(error)
				this.wizardMessageType = 'error'
				this.focusWizardRoot()
			}
		},

		resetWizardState() {
			this.stopPairingPolling()
			this.wizardSessionId = ''
			this.wizardStep = ''
			this.wizardPairCode = ''
			this.wizardAccountName = ''
			this.wizardAccountAvatarUrl = ''
			this.wizardDeviceStrategy = 'use_existing'
			this.wizardDeviceId = ''
			this.wizardPhone = ''
			this.wizardDevices = []
			this.wizardMessage = t('twofactor_gateway', 'Use the guided setup to validate API, choose device strategy, and complete pairing.')
			this.wizardMessageType = 'info'
			this.focusWizardRoot()
		},

		normalizeMessageType(response: InteractiveSetupResponse): 'info' | 'success' | 'warning' | 'error' {
			const responseType = response.messageType
			if (responseType === 'info' || responseType === 'success' || responseType === 'warning' || responseType === 'error') {
				return responseType
			}

			switch (response.status) {
			case 'done':
				return 'success'
			case 'error':
				return 'error'
			case 'needs_input':
			case 'pending':
			case 'cancelled':
			default:
				return 'info'
			}
		},

		normalizeErrorMessage(error: unknown): string {
			const fallback = t('twofactor_gateway', 'Unable to complete guided setup right now. Please try again.')
			if (error instanceof Error && error.message.trim() !== '') {
				return error.message
			}

			return fallback
		},

		applyWizardResponse(response: InteractiveSetupResponse) {
			this.wizardMessage = response.message ?? this.wizardMessage
			this.wizardMessageType = this.normalizeMessageType(response)
			if (response.sessionId) {
				this.wizardSessionId = response.sessionId
			}
			if (response.step) {
				this.wizardStep = response.step
			}
			const pairCode = response.data?.pair_code
			if (typeof pairCode === 'string') {
				this.wizardPairCode = pairCode
			}
			const devices = response.data?.devices
			if (Array.isArray(devices)) {
				this.wizardDevices = devices as WizardDevice[]
				if (!this.wizardDeviceId && this.wizardDevices.length > 0) {
					this.wizardDeviceId = this.wizardDevices[0].id
				}
			}

			const account = response.data?.account
			if (account && typeof account === 'object') {
				const accountName = (account as Record<string, unknown>).account_name
				const accountAvatarUrl = (account as Record<string, unknown>).account_avatar_url
				this.wizardAccountName = typeof accountName === 'string' ? accountName : ''
				this.wizardAccountAvatarUrl = typeof accountAvatarUrl === 'string' ? accountAvatarUrl : ''
			}

			if (response.status === 'done') {
				this.stopPairingPolling()
				this.wizardStep = ''
				this.wizardSessionId = ''
				if (response.config) {
					this.$emit('merge-config', response.config)
					this.$emit('setup-completed', response.config)
					if (response.config.phone) {
						this.wizardPhone = response.config.phone
					}
				}
			}

			if (response.step === 'pairing' && response.status !== 'error' && response.status !== 'done' && !this.pairingPolling) {
				this.startPairingPolling()
			}

			if (response.status === 'error') {
				this.stopPairingPolling()
			}

			this.focusWizardRoot()
		},

		async startWizard() {
			this.$emit('merge-config', {
				base_url: this.bootstrapBaseUrl,
				device_name: this.bootstrapDeviceName,
				username: this.bootstrapUsername,
				password: this.bootstrapPassword,
			})
			this.wizardLoading = true
			try {
				this.wizardMessage = t('twofactor_gateway', 'Starting guided setup…')
				this.wizardMessageType = 'info'
				const response = await startInteractiveSetup(this.gatewayId, {
					base_url: this.bootstrapBaseUrl,
					provider: this.providerId,
					username: this.bootstrapUsername,
					password: this.bootstrapPassword,
					device_name: this.bootstrapDeviceName,
				})
				this.applyWizardResponse(response)
			} catch (error) {
				this.wizardMessage = this.normalizeErrorMessage(error)
				this.wizardMessageType = 'error'
				this.focusWizardRoot()
			} finally {
				this.wizardLoading = false
			}
		},

		async runWizardStep(action: string, input: Record<string, unknown> = {}) {
			if (!this.wizardSessionId) {
				return
			}

			this.wizardLoading = true
			try {
				this.wizardMessage = t('twofactor_gateway', 'Processing guided setup step…')
				this.wizardMessageType = 'info'
				const response = await interactiveSetupStep(this.gatewayId, this.wizardSessionId, action, input)
				this.applyWizardResponse(response)
			} catch (error) {
				this.wizardMessage = this.normalizeErrorMessage(error)
				this.wizardMessageType = 'error'
				this.focusWizardRoot()
			} finally {
				this.wizardLoading = false
			}
		},

		async cancelWizard() {
			this.stopPairingPolling()
			if (!this.wizardSessionId) {
				this.resetWizardState()
				return
			}

			this.wizardLoading = true
			try {
				await cancelInteractiveSetup(this.gatewayId, this.wizardSessionId)
				this.resetWizardState()
			} catch (error) {
				this.wizardMessage = this.normalizeErrorMessage(error)
				this.wizardMessageType = 'error'
				this.focusWizardRoot()
			} finally {
				this.wizardLoading = false
			}
		},
	},
})
</script>

<style lang="scss" scoped>
.modal-wizard {
	padding: 0.75rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	display: flex;
	flex-direction: column;
	gap: 0.75rem;

	h3 {
		margin: 0;
	}

	.wizard-message {
		margin: 0;
	}

	.wizard-note-card {
		margin: 0;

		:deep(.notecard__content) {
			overflow-wrap: anywhere;
			word-break: break-word;
		}

		.wizard-note-card__message {
			margin: 0;
			white-space: pre-wrap;
		}
	}

	.wizard-account-card {
		margin: 0;
	}

	select {
		padding: 0.5rem;
		border-radius: var(--border-radius-element);
		border: 1px solid var(--color-border-dark);
	}
}

.modal-field {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;

	label {
		font-weight: 600;
	}
}

.wizard-actions-inline {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
}

.wizard-pairing-section {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.wizard-account-summary {
	display: flex;
	align-items: center;
	gap: 0.75rem;
}

.wizard-account-summary__text {
	display: flex;
	flex-direction: column;
	gap: 0.125rem;

	strong {
		font-size: 0.875rem;
	}

	span {
		font-size: 0.95rem;
	}
}

.wizard-pairing-phone,
.wizard-pairing-code {
	display: flex;
	gap: 0.5rem;
	align-items: center;
}

.wizard-pairing-code-value {
	font-size: 1.4rem;
	letter-spacing: 0.15em;
}

.wizard-pairing-polling {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;

	.wizard-pairing-polling-label {
		color: var(--color-text-maxcontrast);
		font-size: 0.875rem;
	}
}
</style>
