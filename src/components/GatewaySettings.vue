<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcLoadingIcon v-if="loading" :size="20" />
		<div v-else>
			<p v-if="state === 0">
				<slot name="instructions" />
				{{ t('twofactor_gateway', 'You are not using {displayName} for two-factor authentication at the moment.', {displayName: displayName}) }}
				<NcButton @click="enable">
					{{ t('twofactor_gateway', 'Enable') }}
				</NcButton>
			</p>
			<p v-if="state === 1">
				<slot name="instructions" />
				<strong v-if="verificationError.length">
					{{ t('twofactor_gateway', 'Could not verify your code. Please try again.') }}
				</strong>
				{{ t('twofactor_gateway', 'Enter your identification (e.g. phone number to start the verification):') }}
				<NcTextField v-model="identifier"
					class="input"
					spellcheck="false"
					:error="verificationError.length > 0"
					:helper-text="verificationError" />
				<NcButton @click="verify">
					{{ t('twofactor_gateway', 'Verify') }}
				</NcButton>
			</p>
			<p v-if="state === 2">
				{{ t('twofactor_gateway', 'A confirmation code has been sent to {phone}. Please insert the code here:', {phone: phoneNumber}) }}
				<NcTextField v-model="confirmationCode"
					class="input" />
				<NcButton @click="confirm">
					{{ t('twofactor_gateway', 'Confirm') }}
				</NcButton>
			</p>
			<p v-if="state === 3">
				{{ t('twofactor_gateway', 'Your account was successfully configured to receive messages via {displayName}.', {displayName: displayName}) }}
				<NcButton @click="disable">
					{{ t('twofactor_gateway', 'Disable') }}
				</NcButton>
			</p>
		</div>
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
	name: 'GatewaySettings',
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
	},

	setup() {
		return {
			t,
		}
	},
	data() {
		return {
			loading: false,
			state: 0,
			isAvailable: false,
			phoneNumber: '',
			confirmationCode: '',
			identifier: '',
			verificationError: '',
		}
	},
	mounted() {
		axios.get(generateOcsUrl('/apps/twofactor_gateway/settings/{gateway}/verification', { gateway: this.gatewayName }))
			.then(({ data }) => {
				console.debug('loaded state for gateway ' + this.gatewayName, data.ocs.data)
				this.state = data.ocs.data.state
				this.phoneNumber = data.ocs.data.phoneNumber
			})
			.catch(err => console.info(this.gatewayName + ' gateway is not available', err))
			.finally(() => { this.loading = false })
	},
	methods: {
		enable() {
			this.state = 1
			this.verificationError = ''
			this.loading = false
		},
		verify() {
			this.loading = true
			this.verificationError = ''
			axios.post(generateOcsUrl('/apps/twofactor_gateway/settings/{gateway}/verification/start', { gateway: this.gatewayName }), {
				identifier: this.identifier,
			})
				.then(({ data }) => {
					this.state = 2
					this.phoneNumber = data.ocs.data.phoneNumber
				})
				.catch(({ response }) => {
					console.debug(response.data)
					this.state = 1
					this.verificationError = response?.data?.message ?? ''
				})
				.finally(() => { this.loading = false })
		},
		confirm() {
			this.loading = true

			axios.post(generateOcsUrl('/apps/twofactor_gateway/settings/{gateway}/verification/finish', { gateway: this.gatewayName }), {
				verificationCode: this.confirmationCode,
			})
				.then(() => {
					this.state = 3
				})
				.catch(({ response }) => {
					this.state = 1
					this.verificationError = response?.data?.ocs?.data?.message ?? ''
				})
				.finally(() => { this.loading = false })
		},
		disable() {
			this.loading = true
			axios.delete(generateOcsUrl('/apps/twofactor_gateway/settings/{gateway}/verification', { gateway: this.gatewayName }))
				.then(data => {
					this.state = data.ocs.data.state
					this.phoneNumber = data.ocs.data.phoneNumber
				})
				.catch(console.error.bind(this))
				.finally(() => { this.loading = false })
		},
	},
}
</script>

<style lang="scss">
li:has(#twofactor-gateway-telegram-is-complete[value="0"]),
li:has(#twofactor-gateway-sms-is-complete[value="0"]),
li:has(#twofactor-gateway-xmpp-is-complete[value="0"]),
li:has(#twofactor-gateway-signal-is-complete[value="0"]) {
	display: none;
}
</style>
<style lang="scss" scoped>
	.icon-loading-small {
		padding-inline-start: 15px;
	}
	.input {
		display: flex;
		flex-direction: column;
		gap: 1rem;
		max-width: 400px;
	}
</style>
