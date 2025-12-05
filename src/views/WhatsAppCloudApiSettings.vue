<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="whatsapp-cloud-api-settings">
		<h2>{{ t('twofactor_gateway', 'WhatsApp Cloud API Configuration') }}</h2>

		<NcNoteCard type="info" class="info-note">
			{{ t('twofactor_gateway', 'Configure the Meta/Facebook WhatsApp Cloud API to enable two-factor authentication via WhatsApp.') }}
		</NcNoteCard>

		<div class="settings-section">
			<!-- Número de Telefone -->
			<div class="field-group">
				<label for="phone-number-id">
					{{ t('twofactor_gateway', 'Phone Number ID') }}
					<span class="required">*</span>
				</label>
				<p class="field-description">
					{{ t('twofactor_gateway', 'The ID of your WhatsApp phone number from Meta Business Account') }}
				</p>
				<NcTextField
					id="phone-number-id"
					v-model="formData.phoneNumberId"
					:placeholder="t('twofactor_gateway', 'e.g., 1234567890')"
					@update:model-value="markDirty" />
			</div>

			<!-- ID da Conta WhatsApp Business -->
			<div class="field-group">
				<label for="business-account-id">
					{{ t('twofactor_gateway', 'WhatsApp Business Account ID') }}
					<span class="required">*</span>
				</label>
				<p class="field-description">
					{{ t('twofactor_gateway', 'The ID of your WhatsApp Business Account from Meta Business Manager') }}
				</p>
				<NcTextField
					id="business-account-id"
					v-model="formData.businessAccountId"
					:placeholder="t('twofactor_gateway', 'e.g., 1234567890')"
					@update:model-value="markDirty" />
			</div>

			<!-- Chave da API -->
			<div class="field-group">
				<label for="api-key">
					{{ t('twofactor_gateway', 'API Access Token') }}
					<span class="required">*</span>
				</label>
				<p class="field-description">
					{{ t('twofactor_gateway', 'Your Meta Graph API token with whatsapp_business_messaging permission') }}
				</p>
				<NcTextField
					id="api-key"
					v-model="formData.apiKey"
					:placeholder="t('twofactor_gateway', 'Paste your API token here')"
					type="password"
					show-password
					@update:model-value="markDirty" />
			</div>

			<!-- Endpoint da API (Opcional) -->
			<div class="field-group">
				<label for="api-endpoint">
					{{ t('twofactor_gateway', 'API Endpoint') }}
					<span class="optional">({{ t('twofactor_gateway', 'optional') }})</span>
				</label>
				<p class="field-description">
					{{ t('twofactor_gateway', 'Default: https://graph.facebook.com. Change only if using a custom endpoint.') }}
				</p>
				<NcTextField
					id="api-endpoint"
					v-model="formData.apiEndpoint"
					:placeholder="t('twofactor_gateway', 'https://graph.facebook.com')"
					@update:model-value="markDirty" />
			</div>
		</div>

		<!-- Botões de Ação -->
		<div class="actions">
			<NcButton
				:disabled="!isDirty || isSaving || !isFormValid"
				:aria-busy="isSaving"
				@click="save">
				<template #icon>
					<NcLoadingIcon v-if="isSaving" :size="20" />
					<Check v-else :size="20" />
				</template>
				{{ t('twofactor_gateway', 'Save Configuration') }}
			</NcButton>

			<NcButton
				v-if="isConfigured"
				type="tertiary"
				:disabled="isSaving"
				@click="testConfiguration">
				<template #icon>
					<NcLoadingIcon v-if="isTesting" :size="20" />
					<CheckCircle v-else :size="20" />
				</template>
				{{ t('twofactor_gateway', 'Test Connection') }}
			</NcButton>

			<NcButton
				v-if="isDirty"
				type="secondary"
				:disabled="isSaving"
				@click="reset">
				{{ t('twofactor_gateway', 'Cancel') }}
			</NcButton>
		</div>

		<!-- Status Message -->
		<NcNoteCard v-if="statusMessage" :type="statusType" class="status-message">
			{{ statusMessage }}
		</NcNoteCard>

		<!-- Instruções de Configuração -->
		<div v-if="!isConfigured" class="instructions">
			<h3>{{ t('twofactor_gateway', 'How to Configure') }}</h3>
			<ol>
				<li>
					{{ t('twofactor_gateway', 'Go to Meta Business Manager') }}
					<a href="https://business.facebook.com" target="_blank" rel="noopener noreferrer">
						{{ t('twofactor_gateway', 'https://business.facebook.com') }}
					</a>
				</li>
				<li>{{ t('twofactor_gateway', 'Navigate to WhatsApp Manager → Phone Numbers') }}</li>
				<li>{{ t('twofactor_gateway', 'Copy your Phone Number ID') }}</li>
				<li>{{ t('twofactor_gateway', 'Go to Settings → Business Account → Copy your Account ID') }}</li>
				<li>
					{{ t('twofactor_gateway', 'Create a Graph API token in your app settings') }}
					<a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started/" target="_blank" rel="noopener noreferrer">
						{{ t('twofactor_gateway', 'Documentation') }}
					</a>
				</li>
				<li>{{ t('twofactor_gateway', 'Paste all credentials above and save') }}</li>
			</ol>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
/* eslint-disable-next-line n/no-extraneous-import */
import { showSuccess, showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { t } from '@nextcloud/l10n'
/* eslint-disable-next-line n/no-extraneous-import */
import Check from 'vue-material-design-icons/Check.vue'
/* eslint-disable-next-line n/no-extraneous-import */
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'

const formData = ref({
	phoneNumberId: '',
	businessAccountId: '',
	apiKey: '',
	apiEndpoint: '',
})

const initialData = ref({ ...formData.value })
const isDirty = computed(() => JSON.stringify(formData.value) !== JSON.stringify(initialData.value))
const isSaving = ref(false)
const isTesting = ref(false)
const statusMessage = ref('')
const statusType = ref<'success' | 'error' | 'warning' | 'info'>('info')

const isConfigured = computed(() => formData.value.phoneNumberId && formData.value.apiKey && formData.value.businessAccountId)
const isFormValid = computed(() => isConfigured.value)

function markDirty() {
	// Just triggers reactivity
}

async function loadConfiguration() {
	try {
		const response = await axios.get(generateOcsUrl('/apps/twofactor_gateway/api/v1/whatsapp/configuration'))
		if (response.data.ocs?.data) {
			const config = response.data.ocs.data
			formData.value = {
				phoneNumberId: config.phone_number_id || '',
				businessAccountId: config.business_account_id || '',
				apiKey: config.api_key || '',
				apiEndpoint: config.api_endpoint || '',
			}
			initialData.value = { ...formData.value }
		}
	} catch (error) {
		console.debug('No configuration found or error loading it', error)
	}
}

async function save() {
	if (!isFormValid.value) return

	isSaving.value = true
	statusMessage.value = ''

	try {
		await axios.post(generateOcsUrl('/apps/twofactor_gateway/api/v1/whatsapp/configuration'), {
			phone_number_id: formData.value.phoneNumberId,
			business_account_id: formData.value.businessAccountId,
			api_key: formData.value.apiKey,
			api_endpoint: formData.value.apiEndpoint || 'https://graph.facebook.com',
		})

		initialData.value = { ...formData.value }
		statusMessage.value = t('twofactor_gateway', 'Configuration saved successfully')
		statusType.value = 'success'
		showSuccess(t('twofactor_gateway', 'WhatsApp configuration saved'))
	} catch (error) {
		const errorMsg = error.response?.data?.ocs?.meta?.message || t('twofactor_gateway', 'Failed to save configuration')
		statusMessage.value = errorMsg
		statusType.value = 'error'
		showError(errorMsg)
		console.error('Error saving configuration:', error)
	} finally {
		isSaving.value = false
	}
}

async function testConfiguration() {
	if (!isConfigured.value) return

	isTesting.value = true
	statusMessage.value = ''

	try {
		await axios.post(generateOcsUrl('/apps/twofactor_gateway/api/v1/whatsapp/test'), {
			phone_number_id: formData.value.phoneNumberId,
			business_account_id: formData.value.businessAccountId,
			api_key: formData.value.apiKey,
			api_endpoint: formData.value.apiEndpoint || 'https://graph.facebook.com',
		})

		statusMessage.value = t('twofactor_gateway', 'Connection test successful!')
		statusType.value = 'success'
		showSuccess(t('twofactor_gateway', 'WhatsApp connection test successful'))
	} catch (error) {
		const errorMsg = error.response?.data?.ocs?.meta?.message || t('twofactor_gateway', 'Connection test failed')
		statusMessage.value = errorMsg
		statusType.value = 'error'
		showError(errorMsg)
		console.error('Error testing configuration:', error)
	} finally {
		isTesting.value = false
	}
}

function reset() {
	formData.value = { ...initialData.value }
	statusMessage.value = ''
}

// Load configuration on mount
loadConfiguration()
</script>

<style scoped lang="scss">
.whatsapp-cloud-api-settings {
	max-width: 900px;
	padding: 20px;

	h2 {
		margin-bottom: 20px;
		font-size: 1.5rem;
		font-weight: 600;
	}

	.info-note {
		margin-bottom: 30px;
	}

	.settings-section {
		background: var(--color-background-secondary);
		border-radius: 8px;
		padding: 20px;
		margin-bottom: 20px;

		.field-group {
			margin-bottom: 25px;

			&:last-child {
				margin-bottom: 0;
			}

			label {
				display: block;
				font-weight: 600;
				margin-bottom: 8px;
				color: var(--color-text);

				.required {
					color: var(--color-error);
					font-weight: bold;
				}

				.optional {
					color: var(--color-text-maxcontrast);
					font-size: 0.9em;
				}
			}

			.field-description {
				margin: 0 0 8px 0;
				font-size: 0.9rem;
				color: var(--color-text-maxcontrast);
			}
		}
	}

	.actions {
		display: flex;
		gap: 10px;
		margin-bottom: 20px;
		flex-wrap: wrap;

		:deep(button) {
			min-width: 150px;
		}
	}

	.status-message {
		margin-bottom: 20px;
	}

	.instructions {
		background: var(--color-background-secondary);
		border-radius: 8px;
		padding: 20px;
		margin-top: 30px;

		h3 {
			margin-bottom: 15px;
			font-size: 1.1rem;
			font-weight: 600;
		}

		ol {
			margin-left: 20px;
			line-height: 1.8;

			li {
				margin-bottom: 10px;

				a {
					color: var(--color-primary);
					text-decoration: none;

					&:hover {
						text-decoration: underline;
					}
				}
			}
		}
	}
}
</style>
