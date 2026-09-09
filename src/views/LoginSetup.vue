<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div v-if="!isComplete">
			<p>
				{{ t('twofactor_gateway', '{displayName} is not available. Please ask your administrator to finish setting it up.', {displayName: displayName}) }}
			</p>
		</div>
		<div v-else>
			<NcLoadingIcon v-if="loading" :size="20" />
			<div v-else>
				<div v-if="state === 1" class="login-setup-step">
					<strong v-if="verificationError.length">
						{{ t('twofactor_gateway', 'Could not verify your code. Please try again.') }}
					</strong>
					<p>{{ t('twofactor_gateway', 'Enter your identification (e.g. phone number to start the verification):') }}</p>
					<form @submit.prevent="verify">
						<NcTextField v-model="identifier"
							class="input"
							:spellcheck="false"
							:error="verificationError.length > 0"
							:helper-text="verificationError" />
						<NcButton type="submit" :disabled="submitting">
							<template #icon>
								<NcLoadingIcon v-if="submitting" :size="20" />
							</template>
							{{ t('twofactor_gateway', 'Verify') }}
						</NcButton>
					</form>
				</div>
				<div v-if="state === 2" class="login-setup-step">
					<p>{{ t('twofactor_gateway', 'A confirmation code has been sent to {phone}. Please insert the code here:', {phone: phoneNumber}) }}</p>
					<form @submit.prevent="confirm">
						<NcTextField v-model="confirmationCode"
							class="input"
							:spellcheck="false" />
						<NcButton type="submit" :disabled="submitting">
							<template #icon>
								<NcLoadingIcon v-if="submitting" :size="20" />
							</template>
							{{ t('twofactor_gateway', 'Confirm') }}
						</NcButton>
					</form>
				</div>
				<div v-if="state === 3" class="login-setup-step">
					<p>{{ t('twofactor_gateway', 'Your account was successfully configured to receive messages via {displayName}.', {displayName: displayName}) }}</p>
					<NcLoadingIcon :size="20" />
				</div>
			</div>
		</div>
		<form ref="redirectForm" method="POST">
			<input type="hidden" name="requesttoken" :value="requestToken">
		</form>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { t } from '@nextcloud/l10n'

export default {
	name: 'LoginSetup',
	components: {
		NcButton,
		NcLoadingIcon,
		NcTextField,
	},
	props: {
		gatewayName: {
			type: String,
			required: true,
		},
		displayName: {
			type: String,
			required: true,
		},
		isComplete: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		return {
			t,
		}
	},
	data() {
		return {
			loading: true,
			submitting: false,
			state: 1,
			phoneNumber: '',
			confirmationCode: '',
			identifier: '',
			verificationError: '',
			requestToken: window.OC?.requestToken ?? '',
		}
	},
	mounted() {
		if (!this.isComplete) {
			this.loading = false
			return
		}
		axios.get(generateOcsUrl('/apps/twofactor_gateway/settings/{gateway}/verification', { gateway: this.gatewayName }))
			.then(({ data }) => {
				if (data.state === 2) {
					this.state = 2
					this.phoneNumber = data.phoneNumber
				} else {
					this.state = 1
				}
			})
			.catch(err => console.info(this.gatewayName + ' gateway is not available', err))
			.finally(() => { this.loading = false })
	},
	methods: {
		verify() {
			this.submitting = true
			this.verificationError = ''
			axios.post(generateOcsUrl('/apps/twofactor_gateway/settings/{gateway}/verification/start', { gateway: this.gatewayName }), {
				identifier: this.identifier,
			})
				.then(({ data }) => {
					this.state = 2
					this.phoneNumber = data.phoneNumber
				})
				.catch(({ response }) => {
					this.state = 1
					this.verificationError = response?.data?.ocs?.data?.message ?? ''
				})
				.finally(() => { this.submitting = false })
		},
		confirm() {
			this.submitting = true

			axios.post(generateOcsUrl('/apps/twofactor_gateway/settings/{gateway}/verification/finish', { gateway: this.gatewayName }), {
				verificationCode: this.confirmationCode,
			})
				.then(() => {
					this.state = 3
					this.$nextTick(() => this.$refs.redirectForm.submit())
				})
				.catch(({ response }) => {
					this.state = 1
					this.verificationError = response?.data?.ocs?.data?.message ?? ''
				})
				.finally(() => { this.submitting = false })
		},
	},
}
</script>

<style lang="scss" scoped>
	.icon-loading-small {
		padding-inline-start: 15px;
	}

	.login-setup-step {
		display: flex;
		flex-direction: column;
		align-items: center;
		text-align: center;
		gap: 1rem;
	}

	.login-setup-step form {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 1rem;
	}

	.input {
		width: 100%;
		max-width: 400px;
	}
</style>
