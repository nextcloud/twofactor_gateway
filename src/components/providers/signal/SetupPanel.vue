<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="wizardRoot" class="modal-wizard" tabindex="-1">
		<h3>{{ t('twofactor_gateway', 'Guided Signal Setup') }}</h3>
		<NcNoteCard :type="wizardMessageType" class="wizard-note-card">
			<p class="wizard-note-card__message">{{ wizardMessage }}</p>
		</NcNoteCard>

		<template v-if="!wizardSessionId">
			<div class="modal-field">
				<NcTextField
					v-model="bootstrapUrl"
					:label="t('twofactor_gateway', 'Signal gateway URL:')"
					:required="true"
					:placeholder="t('twofactor_gateway', 'e.g. http://signal-gateway:8080')" />
			</div>
		</template>

		<div v-if="wizardStep === 'scan_qr' && wizardQrSvg" class="wizard-qr-section">
			<div class="wizard-qr-wrapper" v-html="wizardQrSvg" />
			<p class="wizard-qr-instructions">
				{{ t('twofactor_gateway', 'Open Signal → Settings → Linked Devices → Link New Device and scan this code.') }}
			</p>
			<div v-if="qrPolling" class="wizard-qr-polling">
				<span>{{ t('twofactor_gateway', 'Waiting for Signal device link confirmation…') }}</span>
				<NcProgressBar
					:value="Math.round((pollAttempt / pollMaxAttempts) * 100)"
					type="linear"
					size="small" />
			</div>
		</div>

		<div class="wizard-actions-inline">
			<NcButton
				v-if="!wizardSessionId"
				variant="secondary"
				:disabled="wizardLoading || !canStart || !bootstrapUrl.trim()"
				@click="startWizard">
				<template #icon>
					<NcLoadingIcon v-if="wizardLoading" :size="20" />
				</template>
				{{ wizardLoading ? t('twofactor_gateway', 'Starting guided setup…') : t('twofactor_gateway', 'Start guided setup') }}
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
				@click="runWizardStep('poll_link')">
				<template #icon>
					<NcLoadingIcon v-if="wizardLoading" :size="20" />
				</template>
				{{ t('twofactor_gateway', 'Check link status') }}
			</NcButton>
		</div>
	</div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
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
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
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
			wizardMessage: t('twofactor_gateway', 'Enter the Signal gateway URL and follow the instructions to link a Signal account via QR code.'),
			wizardMessageType: 'info' as 'info' | 'success' | 'warning' | 'error',
			wizardQrSvg: '',
			pollingTimer: null as ReturnType<typeof setTimeout> | null,
			pollAttempt: 0,
			pollMaxAttempts: 60,
			qrPolling: false,
			bootstrapUrl: this.config.url ?? '',
		}
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
			case 'done': return 'success'
			case 'error': return 'error'
			default: return 'info'
			}
		},

		normalizeErrorMessage(error: unknown): string {
			if (error instanceof Error && error.message.trim() !== '') {
				return error.message
			}
			return t('twofactor_gateway', 'Unable to complete guided setup right now. Please try again.')
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
				this.wizardMessage = t('twofactor_gateway', 'Timed out waiting for Signal confirmation. Restart setup to get a fresh QR code.')
				this.wizardMessageType = 'warning'
				return
			}
			this.pollingTimer = setTimeout(() => { this.pollOnce() }, 3000)
		},

		async pollOnce() {
			if (!this.wizardSessionId || this.wizardStep !== 'scan_qr') {
				this.stopPolling()
				return
			}
			this.pollAttempt++
			try {
				const response = await interactiveSetupStep(
					this.gatewayId,
					this.wizardSessionId,
					'poll_link',
					{},
				)
				this.applyWizardResponse(response)
			} catch (e) {
				// Network hiccup — keep polling silently
			}
			if (this.wizardStep === 'scan_qr') {
				this.schedulePoll()
			}
		},

		applyWizardResponse(response: InteractiveSetupResponse) {
			this.wizardMessageType = this.normalizeMessageType(response)
			this.wizardMessage = response.message ?? ''
			this.wizardStep = response.step ?? ''

			if (response.sessionId) {
				this.wizardSessionId = response.sessionId
			}

			if (response.data?.qr_svg) {
				this.wizardQrSvg = response.data.qr_svg as string
			}

			if (response.status === 'done') {
				this.stopPolling()
				if (response.config) {
					this.$emit('merge-config', response.config)
				}
				this.$emit('setup-completed')
				this.wizardSessionId = ''
			}
		},

		async startWizard() {
			this.wizardLoading = true
			try {
				const response = await startInteractiveSetup(this.gatewayId, {
					url: this.bootstrapUrl.trim(),
				})
				this.applyWizardResponse(response)
				if (this.wizardStep === 'scan_qr') {
					this.startPolling()
				}
			} catch (e) {
				this.wizardMessage = this.normalizeErrorMessage(e)
				this.wizardMessageType = 'error'
			} finally {
				this.wizardLoading = false
			}
			this.focusWizardRoot()
		},

		async runWizardStep(action: string, input: Record<string, string> = {}) {
			if (!this.wizardSessionId) return
			this.wizardLoading = true
			this.stopPolling()
			try {
				const response = await interactiveSetupStep(this.gatewayId, this.wizardSessionId, action, input)
				this.applyWizardResponse(response)
				if (this.wizardStep === 'scan_qr') {
					this.startPolling()
				}
			} catch (e) {
				this.wizardMessage = this.normalizeErrorMessage(e)
				this.wizardMessageType = 'error'
			} finally {
				this.wizardLoading = false
			}
			this.focusWizardRoot()
		},

		async cancelWizard() {
			this.stopPolling()
			if (this.wizardSessionId) {
				try {
					await cancelInteractiveSetup(this.gatewayId, this.wizardSessionId)
				} catch (_) {
					// ignore
				}
			}
			this.wizardSessionId = ''
			this.wizardStep = ''
			this.wizardQrSvg = ''
			this.wizardMessage = t('twofactor_gateway', 'Enter the Signal gateway URL and follow the instructions to link a Signal account via QR code.')
			this.wizardMessageType = 'info'
		},
	},
})
</script>

<style scoped>
.modal-wizard {
	padding: 8px 0;
	outline: none;
}

.wizard-note-card {
	margin-bottom: 12px;
}

.modal-field {
	margin-bottom: 12px;
}

.wizard-qr-section {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	margin: 8px 0 16px;
}

.wizard-qr-wrapper :deep(svg) {
	max-width: 280px;
	width: 100%;
	height: auto;
	display: block;
}

.wizard-qr-instructions {
	text-align: center;
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
}

.wizard-qr-polling {
	display: flex;
	flex-direction: column;
	gap: 4px;
	width: 100%;
	max-width: 280px;
	text-align: center;
	font-size: 0.85em;
}

.wizard-actions-inline {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	justify-content: flex-end;
	margin-top: 8px;
}
</style>
