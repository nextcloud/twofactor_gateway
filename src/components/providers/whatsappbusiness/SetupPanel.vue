<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="wizardRoot" class="modal-wizard" tabindex="-1">
		<h3>{{ t('twofactor_gateway', 'Guided WhatsApp Business Setup') }}</h3>
		<NcNoteCard :type="wizardMessageType" class="wizard-note-card">
			<p class="wizard-note-card__message">{{ wizardMessage }}</p>
		</NcNoteCard>

		<template v-if="!wizardSessionId">
			<div class="modal-field">
				<NcPasswordField
					v-model="bootstrapToken"
					:label="t('twofactor_gateway', 'Access token:')"
					:required="true"
					:placeholder="t('twofactor_gateway', 'Paste a System User token with WhatsApp permissions')" />
			</div>
			<div class="modal-field">
				<NcTextField
					v-model="bootstrapApiVersion"
					:label="t('twofactor_gateway', 'Graph API version (optional):')"
					:placeholder="t('twofactor_gateway', 'Default: v22.0')" />
			</div>
			<div class="modal-field">
				<NcTextField
					v-model="bootstrapWabaId"
					:label="t('twofactor_gateway', 'WhatsApp Business account ID (required if token cannot auto-discover):')"
					:placeholder="t('twofactor_gateway', 'Recommended when using System User tokens')" />
			</div>
		</template>

		<div v-if="wizardStep === 'phone_selection'" class="modal-field">
			<label for="wizard-phone-select">{{ t('twofactor_gateway', 'Available phone numbers') }}</label>
			<select id="wizard-phone-select" v-model="wizardSelectedPhone" class="wizard-select">
				<option value="">{{ t('twofactor_gateway', 'Select a phone number...') }}</option>
				<option
					v-for="phone in wizardPhoneNumbers"
					:key="phone.id"
					:disabled="phone.is_selectable === false"
					:value="phone.id">
					{{ formatPhoneOptionLabel(phone) }}
				</option>
			</select>
			<p class="wizard-select-help">{{ t('twofactor_gateway', 'Only phone numbers configured for Cloud API are selectable.') }}</p>
		</div>

		<div v-if="wizardStep === 'template_selection'" class="modal-field">
			<label for="wizard-template-select">{{ t('twofactor_gateway', 'Approved templates') }}</label>
			<select id="wizard-template-select" v-model="wizardSelectedTemplate" class="wizard-select">
				<option value="">{{ t('twofactor_gateway', 'Select a template...') }}</option>
				<option
					v-for="template in wizardTemplates"
					:key="`${template.name}-${template.language}`"
					:disabled="template.is_selectable === false"
					:value="`${template.name}|${template.language}`">
					{{ formatTemplateOptionLabel(template) }}
				</option>
			</select>
			<p class="wizard-select-help">{{ t('twofactor_gateway', 'Only approved templates are selectable.') }}</p>
		</div>

		<div v-if="wizardStep === 'template_selection' && selectedTemplatePreview" class="template-preview">
			<h4 class="template-preview__title">{{ t('twofactor_gateway', 'Template Preview') }}</h4>
			<div class="template-preview__meta">
				<div class="meta-row">
					<span class="meta-label">{{ t('twofactor_gateway', 'Template:') }}</span>
					<span class="meta-value">{{ selectedTemplatePreview.name }}</span>
				</div>
				<div class="meta-row">
					<!-- TRANSLATORS: Label for selecting the template's human language (locale), for example pt_BR or en_US. -->
					<span class="meta-label">{{ t('twofactor_gateway', 'Language:') }}</span>
					<span class="meta-value">{{ selectedTemplatePreview.language }}</span>
				</div>
				<div class="meta-row">
					<span class="meta-label">{{ t('twofactor_gateway', 'Status:') }}</span>
					<span class="meta-value meta-status-approved">{{ selectedTemplatePreview.status }}</span>
				</div>
			</div>

			<div v-if="getTemplateHeader(selectedTemplatePreview)" class="template-preview__section">
				<div class="section-label">{{ t('twofactor_gateway', 'Header') }}</div>
				<div class="section-content">{{ getTemplateHeader(selectedTemplatePreview) }}</div>
			</div>

			<div class="template-preview__section">
				<div class="section-label">{{ t('twofactor_gateway', 'Body') }}</div>
				<div class="section-content template-body">
					<template v-for="(part, idx) in splitTemplateBody(getTemplateBody(selectedTemplatePreview))" :key="idx">
						<span v-if="part.isVariable" class="placeholder">{{ part.text }}</span>
						<span v-else>{{ part.text }}</span>
					</template>
				</div>
				<div class="variable-note">
					💡 {{ t('twofactor_gateway', 'The verification code will be inserted at') }} &#123;&#123;1&#125;&#125;
				</div>
			</div>

			<div v-if="getTemplateFooter(selectedTemplatePreview)" class="template-preview__section">
				<div class="section-label">{{ t('twofactor_gateway', 'Footer') }}</div>
				<div class="section-content footer-text">{{ getTemplateFooter(selectedTemplatePreview) }}</div>
			</div>
		</div>

		<div v-if="wizardStep === 'template_selection' && wizardTemplates.length === 0" class="modal-field">
			<NcTextField
				v-model="wizardManualTemplateName"
				:label="t('twofactor_gateway', 'Template name:')"
				:placeholder="t('twofactor_gateway', 'e.g. libresign_invite_basic_v1')" />
		</div>

		<div v-if="wizardStep === 'template_selection' && wizardTemplates.length === 0" class="modal-field">
			<NcTextField
				v-model="wizardManualTemplateLanguage"
				:label="t('twofactor_gateway', 'Template language code:')"
				:placeholder="t('twofactor_gateway', 'e.g. pt_BR')" />
		</div>

		<div v-if="wizardStep === 'complete'" class="wizard-summary">
			<div class="summary-row">
				<span class="summary-label">{{ t('twofactor_gateway', 'Phone number') }}</span>
				<div class="summary-value summary-phone-display">
					<div class="phone-display-number">{{ wizardResult.phone_number_display || wizardResult.phone_number_id }}</div>
					<div v-if="wizardResult.phone_number_display" class="phone-display-id">ID: {{ wizardResult.phone_number_id }}</div>
				</div>
			</div>
			<div class="summary-row">
				<span class="summary-label">{{ t('twofactor_gateway', 'Template') }}</span>
				<span class="summary-value">{{ wizardResult.template_name }}</span>
			</div>
			<div class="summary-row">
				<span class="summary-label">{{ t('twofactor_gateway', 'Language') }}</span>
				<span class="summary-value">{{ wizardResult.template_language }}</span>
			</div>
		</div>

		<div class="wizard-actions-inline">
			<NcButton
				v-if="!wizardSessionId"
				variant="primary"
				:disabled="wizardLoading || !canStart || !bootstrapToken.trim()"
				@click="startWizard">
				<template #icon>
					<NcLoadingIcon v-if="wizardLoading" :size="20" />
				</template>
				{{ startWizardButtonLabel() }}
			</NcButton>

			<NcButton
				v-if="wizardSessionId && wizardStep === 'phone_selection'"
				variant="primary"
				:disabled="wizardLoading || !wizardSelectedPhone"
				@click="discoverTemplates">
				<template #icon>
					<NcLoadingIcon v-if="wizardLoading" :size="20" />
				</template>
				{{ t('twofactor_gateway', 'Discover templates') }}
			</NcButton>

			<NcButton
				v-if="wizardSessionId && wizardStep === 'template_selection'"
				variant="primary"
				:disabled="wizardLoading || !canFinalizeTemplateSelection()"
				@click="finalizeWizard">
				<template #icon>
					<NcLoadingIcon v-if="wizardLoading" :size="20" />
				</template>
				{{ t('twofactor_gateway', 'Use this template') }}
			</NcButton>

			<NcButton
				v-if="wizardSessionId && wizardStep === 'complete'"
				variant="tertiary"
				:disabled="wizardLoading"
				@click="cancelWizard">
				{{ t('twofactor_gateway', 'Start over') }}
			</NcButton>
		</div>
	</div>
</template>

<script lang="ts">
import { defineComponent, type PropType } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { t } from '@nextcloud/l10n'
import {
	cancelInteractiveSetup,
	interactiveSetupStep,
	startInteractiveSetup,
} from '@lib/twofactor-gateway'
import type { InteractiveSetupResponse } from '@lib/twofactor-gateway'

type PhoneNumberOption = {
	id: string
	display_phone_number?: string
	code_verification_status?: string
	platform_type?: string
	is_selectable?: boolean
	unselectable_reason?: string
}

type TemplateOption = {
	name: string
	language: string
	status?: string
	body?: string
	header?: string
	footer?: string
	is_selectable?: boolean
	unselectable_reason?: string
}

export default defineComponent({
	name: 'SetupPanel',
	components: {
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcPasswordField,
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
			wizardSessionId: '',
			wizardStep: '',
			wizardMessage: t('twofactor_gateway', 'Paste the token once. The wizard will query Meta and list the available phone numbers and approved templates automatically.'),
			wizardMessageType: 'info' as 'info' | 'success' | 'warning' | 'error',
			bootstrapToken: this.config.access_token ?? '',
			bootstrapApiVersion: this.config.api_version ?? 'v22.0',
			bootstrapWabaId: '',
			wizardPhoneNumbers: [] as PhoneNumberOption[],
			wizardSelectedPhone: this.config.phone_number_id ?? '',
			wizardTemplates: [] as TemplateOption[],
			wizardSelectedTemplate: '',
			wizardManualTemplateName: this.config.template_name ?? '',
			wizardManualTemplateLanguage: this.config.template_language ?? '',
			wizardResult: {} as Record<string, string>,
		}
	},
	watch: {
		wizardSessionId(val: string) {
			this.$emit('update:wizardActive', val !== '')
		},
	},
	computed: {
		selectedTemplatePreview(): TemplateOption | null {
			if (this.wizardSelectedTemplate === '') {
				return null
			}

			const [name, language] = this.wizardSelectedTemplate.split('|')
			const template = this.wizardTemplates.find((t) => t.name === name && t.language === language)
			return template || null
		},
	},
	methods: {
		focusWizardRoot() {
			this.$nextTick(() => {
				const wizardRoot = this.$refs.wizardRoot as HTMLElement | undefined
				wizardRoot?.focus({ preventScroll: true })
			})
		},

		startWizardButtonLabel(): string {
			if (this.wizardLoading) {
				return t('twofactor_gateway', 'Starting guided setup\u00A0...')
			}

			return t('twofactor_gateway', 'Discover available resources')
		},

		canFinalizeTemplateSelection(): boolean {
			if (this.wizardTemplates.length > 0) {
				return this.wizardSelectedTemplate !== ''
			}

			return this.wizardManualTemplateName.trim() !== '' && this.wizardManualTemplateLanguage.trim() !== ''
		},

		formatPhoneOptionLabel(phone: PhoneNumberOption): string {
			const base = phone.display_phone_number || phone.id
			const tags = [
				(phone.platform_type ?? '').trim(),
				(phone.code_verification_status ?? '').trim(),
			].filter((tag) => tag !== '')
			const suffix = tags.length > 0 ? ` [${tags.join(' | ')}]` : ''
			if (phone.is_selectable === false) {
				const reason = (phone.unselectable_reason ?? '').trim()
				return `${base}${suffix} - ${reason || t('twofactor_gateway', 'Not selectable')}`
			}

			return `${base}${suffix}`
		},

		formatTemplateOptionLabel(template: TemplateOption): string {
			const status = (template.status ?? '').trim()
			const suffix = status !== '' ? ` [${status}]` : ''
			if (template.is_selectable === false) {
				const reason = (template.unselectable_reason ?? '').trim()
				return `${template.name} (${template.language})${suffix} - ${reason || t('twofactor_gateway', 'Not selectable')}`
			}

			return `${template.name} (${template.language})${suffix}`
		},

		splitTemplateBody(body: string): Array<{ text: string; isVariable: boolean }> {
			const parts: Array<{ text: string; isVariable: boolean }> = []
			const regex = /\{\{1\}\}/g
			let lastIndex = 0
			let match

			while ((match = regex.exec(body)) !== null) {
				if (match.index > lastIndex) {
					parts.push({ text: body.substring(lastIndex, match.index), isVariable: false })
				}
				parts.push({ text: '{{1}}', isVariable: true })
				lastIndex = regex.lastIndex
			}

			if (lastIndex < body.length) {
				parts.push({ text: body.substring(lastIndex), isVariable: false })
			}

			return parts.length > 0 ? parts : [{ text: body, isVariable: false }]
		},

		getTemplateBody(template: TemplateOption | null): string {
			return template?.body?.trim() || '(Template body not available)'
		},

		getTemplateHeader(template: TemplateOption | null): string {
			return template?.header?.trim() || ''
		},

		getTemplateFooter(template: TemplateOption | null): string {
			return template?.footer?.trim() || ''
		},

		ensureStepOk(response: InteractiveSetupResponse, fallbackMessage: string): InteractiveSetupResponse {
			if (response.status === 'error') {
				throw new Error(response.message || fallbackMessage)
			}

			return response
		},

		applyResponse(response: InteractiveSetupResponse) {
			this.wizardStep = response.step ?? ''
			if (response.message) {
				this.wizardMessage = response.message
			}
			if (response.messageType) {
				this.wizardMessageType = response.messageType
			}

			const rawResponse = response as InteractiveSetupResponse & {
				phoneNumbers?: PhoneNumberOption[]
				templates?: TemplateOption[]
				result?: Record<string, string>
			}

			if (Array.isArray(rawResponse.phoneNumbers)) {
				this.wizardPhoneNumbers = rawResponse.phoneNumbers
			}
			if (Array.isArray(rawResponse.templates)) {
				this.wizardTemplates = rawResponse.templates
			}
			if (rawResponse.result && typeof rawResponse.result === 'object') {
				this.wizardResult = rawResponse.result
			}
		},

		async startWizard() {
			this.wizardLoading = true
			this.wizardMessageType = 'info'
			this.wizardMessage = t('twofactor_gateway', 'Initializing WhatsApp Business discovery...')

			try {
				const started = this.ensureStepOk(await startInteractiveSetup(this.gatewayId, {
					provider: this.providerId,
				}), t('twofactor_gateway', 'Could not create the setup session.'))
				this.wizardSessionId = started.sessionId ?? ''
				if (this.wizardSessionId === '') {
					throw new Error(started.message ?? t('twofactor_gateway', 'Could not create the setup session.'))
				}

				this.ensureStepOk(await interactiveSetupStep(this.gatewayId, this.wizardSessionId, 'set_credentials', {
					provider: this.providerId,
					token: this.bootstrapToken.trim(),
					apiVersion: this.bootstrapApiVersion.trim() || 'v22.0',
					whatsAppBusinessAccountId: this.bootstrapWabaId.trim(),
				}), t('twofactor_gateway', 'Could not store WhatsApp Business credentials.'))

				const phones = this.ensureStepOk(await interactiveSetupStep(this.gatewayId, this.wizardSessionId, 'discover_phones', {
					provider: this.providerId,
				}), t('twofactor_gateway', 'Failed to discover WhatsApp Business resources.'))
				this.applyResponse(phones)
				this.focusWizardRoot()
			} catch (error) {
				this.wizardMessageType = 'error'
				this.wizardMessage = error instanceof Error ? error.message : t('twofactor_gateway', 'Failed to discover WhatsApp Business resources.')
				this.wizardSessionId = ''
				this.wizardStep = ''
			} finally {
				this.wizardLoading = false
			}
		},

		async discoverTemplates() {
			if (this.wizardSessionId === '' || this.wizardSelectedPhone === '') {
				return
			}

			this.wizardLoading = true
			this.wizardMessageType = 'info'
			this.wizardMessage = t('twofactor_gateway', 'Loading approved templates...')

			try {
				this.ensureStepOk(await interactiveSetupStep(this.gatewayId, this.wizardSessionId, 'select_phone', {
					provider: this.providerId,
					phoneNumberId: this.wizardSelectedPhone,
				}), t('twofactor_gateway', 'Failed to select the phone number for template discovery.'))
				const templates = this.ensureStepOk(await interactiveSetupStep(this.gatewayId, this.wizardSessionId, 'discover_templates', {
					provider: this.providerId,
				}), t('twofactor_gateway', 'Failed to load approved templates.'))
				this.applyResponse(templates)
				this.focusWizardRoot()
			} catch (error) {
				this.wizardMessageType = 'error'
				this.wizardMessage = error instanceof Error ? error.message : t('twofactor_gateway', 'Failed to load approved templates.')
			} finally {
				this.wizardLoading = false
			}
		},

		async finalizeWizard() {
			if (this.wizardSessionId === '' || !this.canFinalizeTemplateSelection()) {
				return
			}

			this.wizardLoading = true
			this.wizardMessageType = 'info'
			this.wizardMessage = t('twofactor_gateway', 'Finishing setup...')

			try {
				let templateName = ''
				let templateLanguage = ''
				if (this.wizardSelectedTemplate !== '') {
					;[templateName, templateLanguage] = this.wizardSelectedTemplate.split('|')
				} else {
					templateName = this.wizardManualTemplateName.trim()
					templateLanguage = this.wizardManualTemplateLanguage.trim()
				}

				const completed = this.ensureStepOk(await interactiveSetupStep(this.gatewayId, this.wizardSessionId, 'finalize', {
					provider: this.providerId,
					templateName,
					templateLanguage,
				}), t('twofactor_gateway', 'Failed to finalize WhatsApp Business setup.'))
				this.applyResponse(completed)
				this.$emit('merge-config', this.wizardResult)
				this.$emit('setup-completed', this.wizardResult)
			} catch (error) {
				this.wizardMessageType = 'error'
				this.wizardMessage = error instanceof Error ? error.message : t('twofactor_gateway', 'Failed to finalize WhatsApp Business setup.')
			} finally {
				this.wizardLoading = false
			}
		},

		async cancelWizard() {
			if (this.wizardSessionId !== '') {
				try {
					await cancelInteractiveSetup(this.gatewayId, this.wizardSessionId, {
						provider: this.providerId,
					})
				} catch {
					// Ignore cancellation cleanup failures.
				}
			}

			this.wizardLoading = false
			this.wizardSessionId = ''
			this.wizardStep = ''
			this.wizardPhoneNumbers = []
			this.wizardSelectedPhone = this.config.phone_number_id ?? ''
			this.wizardTemplates = []
			this.wizardSelectedTemplate = ''
			this.wizardManualTemplateName = this.config.template_name ?? ''
			this.wizardManualTemplateLanguage = this.config.template_language ?? ''
			this.wizardResult = {}
			this.wizardMessageType = 'info'
			this.wizardMessage = t('twofactor_gateway', 'Paste the token once. The wizard will query Meta and list the available phone numbers and approved templates automatically.')
		},
	},
})
</script>

<style scoped lang="scss">
.modal-wizard {
	display: flex;
	flex-direction: column;
	gap: 1rem;
	outline: none;
}

.wizard-note-card__message {
	margin: 0;
}

.wizard-select {
	width: 100%;
	min-height: 44px;
	padding: 0.625rem 0.75rem;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-element);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.wizard-summary {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	padding: 1rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.summary-row {
	display: flex;
	justify-content: space-between;
	gap: 1rem;
}

.summary-label {
	font-weight: 600;
}

.summary-value {
	text-align: right;
	word-break: break-word;
}

.summary-phone-display {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 0.25rem;
}

.phone-display-number {
	font-size: 1.1rem;
	font-weight: 600;
	color: var(--color-main-text);
	font-family: monospace;
}

.phone-display-id {
	font-size: 0.85rem;
	color: var(--color-text-lighter);
	font-family: monospace;
}

.wizard-actions-inline {
	display: flex;
	gap: 0.75rem;
	justify-content: flex-end;
	flex-wrap: wrap;
}

.wizard-select-help {
	margin: 0.25rem 0 0;
	color: var(--color-text-lighter);
	font-size: 0.85rem;
}

.template-preview {
	padding: 1.25rem;
	border: 2px solid var(--color-primary-element);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	animation: slideIn 0.2s ease-out;
}

@keyframes slideIn {
	from {
		opacity: 0;
		transform: translateY(-8px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

.template-preview__title {
	margin: 0 0 0.75rem;
	font-size: 0.95rem;
	font-weight: 600;
	color: var(--color-main-text);
}

.template-preview__meta {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
	margin-bottom: 1rem;
	padding-bottom: 1rem;
	border-bottom: 1px solid var(--color-border);
}

.meta-row {
	display: flex;
	justify-content: space-between;
	gap: 1rem;
	font-size: 0.9rem;
}

.meta-label {
	font-weight: 600;
	color: var(--color-text-lighter);
	min-width: fit-content;
}

.meta-value {
	text-align: right;
	word-break: break-word;
	color: var(--color-main-text);
}

.meta-status-approved {
	display: inline-block;
	padding: 0.25rem 0.5rem;
	background: var(--color-success-container, #d4f8d4);
	color: var(--color-success-text, #000);
	border-radius: 4px;
	font-weight: 600;
	font-size: 0.85rem;
}

.template-preview__section {
	margin-bottom: 1rem;

	&:last-child {
		margin-bottom: 0;
	}
}

.section-label {
	font-size: 0.85rem;
	font-weight: 600;
	color: var(--color-text-lighter);
	margin-bottom: 0.5rem;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.section-content {
	padding: 0.75rem;
	background: var(--color-main-background);
	border-left: 3px solid var(--color-primary-element);
	border-radius: 4px;
	font-size: 0.95rem;
	line-height: 1.5;
	color: var(--color-main-text);
	word-break: break-word;
	white-space: pre-wrap;
}

.template-body {
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell,
		'Helvetica Neue', sans-serif;

	// Highlight the {{1}} placeholder
	::v-deep {
		.placeholder {
			background: var(--color-primary-element);
			color: white;
			padding: 0.2rem 0.4rem;
			border-radius: 3px;
			font-weight: 600;
			font-family: monospace;
		}
	}
}

.footer-text {
	font-size: 0.9rem;
	color: var(--color-text-lighter);
	border-left-color: var(--color-text-lighter);
}

.variable-note {
	margin-top: 0.5rem;
	padding: 0.5rem 0.75rem;
	background: var(--color-info-container, #e3f2fd);
	color: var(--color-info-text, #000);
	border-radius: 4px;
	font-size: 0.85rem;
	line-height: 1.4;
}
</style>
