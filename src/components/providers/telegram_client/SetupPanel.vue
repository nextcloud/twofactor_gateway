<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="wizardRoot" class="modal-wizard" tabindex="-1">
		<h3>{{ t('twofactor_gateway', 'Guided Telegram Client Setup') }}</h3>
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

		<template v-if="!wizardSessionId">
			<div class="modal-field">
				<NcTextField
					v-model="bootstrapApiId"
					:label="t('twofactor_gateway', 'Telegram api_id:')"
					:required="true"
					:placeholder="t('twofactor_gateway', 'From my.telegram.org/apps')" />
			</div>
			<div class="modal-field">
				<NcPasswordField
					v-model="bootstrapApiHash"
					:label="t('twofactor_gateway', 'Telegram api_hash:')"
					:required="true"
					:placeholder="t('twofactor_gateway', 'From my.telegram.org/apps')" />
			</div>
			<div class="modal-field">
				<NcCheckboxRadioSwitch
					:model-value="bootstrapLogEnabled"
					@update:modelValue="bootstrapLogEnabled = !!$event">
					{{ t('twofactor_gateway', 'Save Madeline diagnostic logs') }}
				</NcCheckboxRadioSwitch>
			</div>
			<div class="modal-field">
				<NcTextField
					v-model="bootstrapLogPath"
					:label="t('twofactor_gateway', 'Madeline log file path (optional):')"
					:placeholder="t('twofactor_gateway', 'Default: session directory')"
					:disabled="!bootstrapLogEnabled" />
			</div>
		</template>

		<div v-if="wizardStep === 'scan_qr' && wizardQrSvg" class="wizard-qr-section">
			<div class="wizard-qr-wrapper" v-html="wizardQrSvg" />
			<a
				v-if="wizardQrLink"
				:href="wizardQrLink"
				target="_blank"
				rel="noreferrer noopener"
				class="wizard-qr-link">
				{{ t('twofactor_gateway', 'Open login link') }}
			</a>
			<div v-if="qrPolling" class="wizard-qr-polling">
				<!-- TRANSLATORS: Keep U+00A0 before the ellipsis so it never wraps to a new line, per Nextcloud translation style guide. -->
				<span>{{ t('twofactor_gateway', 'Waiting for Telegram confirmation\u00A0…') }}</span>
				<NcProgressBar
					:value="Math.round((pollAttempt / pollMaxAttempts) * 100)"
					type="linear"
					size="small" />
			</div>
		</div>

		<div v-if="wizardStep === 'enter_password'" class="modal-field">
			<NcPasswordField
				v-model="wizardPassword"
				:label="t('twofactor_gateway', 'Telegram 2FA password:')"
				:required="true"
				:placeholder="t('twofactor_gateway', 'Your Telegram password')" />
		</div>

		<div v-if="wizardStep === 'password_polling'" class="wizard-password-polling">
			<span>{{ t('twofactor_gateway', 'Verifying password with Telegram\u00A0…') }}</span>
			<NcProgressBar
				:value="Math.round((pollAttempt / pollMaxAttempts) * 100)"
				type="linear"
				size="small" />
		</div>

		<div class="wizard-actions-inline">
			<NcButton
				v-if="showRelinkButton"
				variant="tertiary"
				:disabled="wizardLoading || !canStart"
				@click="startWizard(true)">
				{{ t('twofactor_gateway', 'Relink from scratch') }}
			</NcButton>

			<NcButton
				v-if="!wizardSessionId"
				variant="secondary"
				:disabled="wizardLoading || !canStart || !bootstrapApiId.trim() || !bootstrapApiHash.trim()"
				@click="startWizard">
				<template #icon>
					<NcLoadingIcon v-if="wizardLoading" :size="20" />
				</template>
				{{ startWizardButtonLabel() }}
			</NcButton>

			<NcButton
				v-if="wizardSessionId"
				variant="tertiary"
				:disabled="wizardLoading"
				@click="cancelWizard">
				{{ t('twofactor_gateway', 'Back') }}
			</NcButton>

			<NcButton
				v-if="wizardSessionId && wizardStep === 'scan_qr'"
				variant="primary"
				:disabled="wizardLoading"
				@click="runWizardStep('poll_login')">
				<template #icon>
					<NcLoadingIcon v-if="wizardLoading" :size="20" />
				</template>
				{{ t('twofactor_gateway', 'Check login status') }}
			</NcButton>

			<NcButton
				v-if="wizardSessionId && wizardStep === 'enter_password'"
				variant="primary"
				:disabled="wizardLoading || wizardPassword === ''"
				@click="runWizardStep('submit_password', { password: wizardPassword })">
				<template #icon>
					<NcLoadingIcon v-if="wizardLoading" :size="20" />
				</template>
				{{ t('twofactor_gateway', 'Submit password') }}
			</NcButton>

			<NcButton
				v-if="wizardSessionId && wizardStep === 'password_polling'"
				variant="tertiary"
				:disabled="wizardLoading"
				@click="cancelWizard">
				{{ t('twofactor_gateway', 'Cancel') }}
			</NcButton>
		</div>
	</div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { t } from '@nextcloud/l10n'
import {
	cancelInteractiveSetup,
	interactiveSetupStep,
	startInteractiveSetup,
	type InteractiveSetupResponse,
} from '../../../services/adminGatewayApi.ts'

export default defineComponent({
	name: 'SetupPanel',
	components: {
		NcAvatar,
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
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
			wizardMessage: t('twofactor_gateway', 'Provide Telegram API credentials from my.telegram.org/apps, scan the QR code, and wait for login confirmation.'),
			wizardMessageType: 'info' as 'info' | 'success' | 'warning' | 'error',
			wizardQrSvg: '',
			wizardQrLink: '',
			wizardAccountName: '',
			wizardAccountAvatarUrl: '',
			pollingTimer: null as ReturnType<typeof setTimeout> | null,
			pollAttempt: 0,
			pollMaxAttempts: 120,
			qrPolling: false,
			wizardPassword: '',
			bootstrapApiId: this.config.api_id ?? '',
			bootstrapApiHash: this.config.api_hash ?? '',
			bootstrapLogEnabled: ['1', 'true', 'yes', 'on'].includes(String(this.config.madeline_log_enabled ?? '').toLowerCase()),
			bootstrapLogPath: this.config.madeline_log_path ?? '',
		}
	},
	computed: {
		showRelinkButton(): boolean {
			if (this.wizardSessionId !== '' || this.wizardLoading) {
				return false
			}

			return this.wizardMessageType === 'success' && this.wizardMessage.toLowerCase().includes('already logged in')
		},
	},
	watch: {
		wizardSessionId(val: string) {
			this.$emit('update:wizardActive', val !== '')
		},
	},
	beforeUnmount() {
		this.stopPolling()
	},
	methods: {
		startWizardButtonLabel(): string {
			if (this.wizardLoading) {
				// TRANSLATORS: Keep U+00A0 before the ellipsis so it never wraps to a new line, per Nextcloud translation style guide.
				return t('twofactor_gateway', 'Starting guided setup\u00A0…')
			}

			return t('twofactor_gateway', 'Start guided setup')
		},

		focusWizardRoot() {
			this.$nextTick(() => {
				const wizardRoot = this.$refs.wizardRoot as HTMLElement | undefined
				wizardRoot?.focus({ preventScroll: true })
			})
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

		sanitizeCredential(value: string): string {
			return value.replace(/\s+/g, '')
		},

		startPolling() {
			this.stopPolling()
			this.pollAttempt = 0
			this.qrPolling = true
			this.schedulePoll()
		},

		stopPolling() {
			if (this.pollingTimer !== null) {
				clearTimeout(this.pollingTimer)
				this.pollingTimer = null
			}
			this.qrPolling = false
		},

		schedulePoll() {
			if (this.pollAttempt >= this.pollMaxAttempts) {
				this.stopPolling()
				this.wizardMessage = t('twofactor_gateway', 'Timed out waiting for Telegram confirmation. Scan the QR again or restart setup.')
				this.wizardMessageType = 'warning'
				return
			}
			this.pollingTimer = setTimeout(() => { this.pollOnce() }, 3000)
		},

		async pollOnce() {
			if (!this.wizardSessionId || (this.wizardStep !== 'scan_qr' && this.wizardStep !== 'password_polling')) {
				this.stopPolling()
				return
			}
			this.pollAttempt++
			try {
				const response = await interactiveSetupStep(this.gatewayId, this.wizardSessionId, 'poll_login')
				this.applyWizardResponse(response)
				if (this.wizardSessionId && (this.wizardStep === 'scan_qr' || this.wizardStep === 'password_polling') && response.status !== 'error') {
					this.schedulePoll()
				} else {
					this.stopPolling()
				}
			} catch (error) {
				this.stopPolling()
				this.wizardMessage = this.normalizeErrorMessage(error)
				this.wizardMessageType = 'error'
				this.focusWizardRoot()
			}
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
			if (this.wizardStep === 'scan_qr') {
				this.wizardPassword = ''
			}
			if (this.wizardStep !== 'scan_qr' && this.wizardStep !== 'password_polling') {
				this.stopPolling()
			}

			const qrSvg = response.data?.qr_svg
			if (typeof qrSvg === 'string') {
				this.wizardQrSvg = qrSvg
			}
			const qrLink = response.data?.link
			if (typeof qrLink === 'string') {
				this.wizardQrLink = qrLink
			}

			const account = response.data?.account
			if (account && typeof account === 'object') {
				const accountName = (account as Record<string, unknown>).account_name
				const accountAvatarUrl = (account as Record<string, unknown>).account_avatar_url
				this.wizardAccountName = typeof accountName === 'string' ? accountName : ''
				this.wizardAccountAvatarUrl = typeof accountAvatarUrl === 'string' ? accountAvatarUrl : ''
			}

			if (response.status === 'done') {
				this.stopPolling()
				this.wizardSessionId = ''
				this.wizardStep = ''
				this.wizardPassword = ''
				if (response.config) {
					this.$emit('merge-config', response.config)
					this.$emit('setup-completed', response.config)
				}
			}

			if ((response.step === 'scan_qr' || response.step === 'password_polling') && response.status !== 'error' && response.status !== 'done' && !this.qrPolling) {
				this.startPolling()
			}

			if (response.status === 'error') {
				this.stopPolling()
			}

			this.focusWizardRoot()
		},

		async startWizard(forceRelink = false) {
			const sanitizedApiId = this.sanitizeCredential(this.bootstrapApiId)
			const sanitizedApiHash = this.sanitizeCredential(this.bootstrapApiHash)
			const normalizedLogPath = this.bootstrapLogEnabled ? this.bootstrapLogPath.trim() : ''
			this.bootstrapApiId = sanitizedApiId
			this.bootstrapApiHash = sanitizedApiHash
			this.bootstrapLogPath = normalizedLogPath

			this.$emit('merge-config', {
				api_id: sanitizedApiId,
				api_hash: sanitizedApiHash,
				provider: this.providerId,
				madeline_log_enabled: this.bootstrapLogEnabled ? '1' : '0',
				madeline_log_path: normalizedLogPath,
			})
			this.wizardLoading = true
			try {
				// TRANSLATORS: Keep U+00A0 before the ellipsis so it never wraps to a new line, per Nextcloud translation style guide.
				this.wizardMessage = t('twofactor_gateway', 'Starting guided setup\u00A0…')
				this.wizardMessageType = 'info'
				const response = await startInteractiveSetup(this.gatewayId, {
					provider: this.providerId,
					api_id: sanitizedApiId,
					api_hash: sanitizedApiHash,
					madeline_log_enabled: this.bootstrapLogEnabled ? '1' : '0',
					madeline_log_path: normalizedLogPath,
					force_relink: forceRelink ? '1' : '0',
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
				// TRANSLATORS: Keep U+00A0 before the ellipsis so it never wraps to a new line, per Nextcloud translation style guide.
				this.wizardMessage = t('twofactor_gateway', 'Processing guided setup step\u00A0…')
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
			this.stopPolling()
			if (!this.wizardSessionId) {
				return
			}

			this.wizardLoading = true
			try {
				await cancelInteractiveSetup(this.gatewayId, this.wizardSessionId)
				this.wizardSessionId = ''
				this.wizardStep = ''
				this.wizardQrSvg = ''
				this.wizardQrLink = ''
				this.wizardPassword = ''
				this.wizardMessage = t('twofactor_gateway', 'Provide Telegram API credentials from my.telegram.org/apps, scan the QR code, and wait for login confirmation.')
				this.wizardMessageType = 'info'
				this.focusWizardRoot()
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
}

.modal-field {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
}

.wizard-actions-inline {
	display: flex;
	flex-wrap: wrap;
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
}

.wizard-qr-section {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	align-items: flex-start;

	.wizard-qr-wrapper :deep(svg) {
		max-width: min(100%, 18rem);
		height: auto;
		display: block;
	}

	.wizard-qr-link {
		font-weight: 600;
		text-decoration: underline;
	}
}

.wizard-qr-polling {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
	width: min(100%, 22rem);
}
</style>
