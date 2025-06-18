<template>
	<div>
		<div v-if="!isAvailable">
			{{ t('twofactor_gateway', 'The {displayName} gateway is not configured.', {displayName: displayName}) }}
		</div>
		<div v-else-if="loading">
			<span class="icon-loading-small" />
		</div>
		<div v-else>
			<p v-if="state === 0">
				<slot name="instructions" />
				{{ t('twofactor_gateway', 'You are not using {displayName} for two-factor authentication at the moment.', {displayName: displayName}) }}
				<button @click="enable">
					{{ t('twofactor_gateway', 'Enable') }}
				</button>
			</p>
			<p v-if="state === 1">
				<slot name="instructions" />
				<strong v-if="verificationError === true">
					{{ t('twofactor_gateway', 'Could not verify your code. Please try again.') }}
				</strong>
				{{ t('twofactor_gateway', 'Enter your identification (e.g. phone number to start the verification):') }}
				<input v-model="identifier">
				<button @click="verify">
					{{ t('twofactor_gateway', 'Verify') }}
				</button>
			</p>
			<p v-if="state === 2">
				{{ t('twofactor_gateway', 'A confirmation code has been sent to {phone}. Please insert the code here:', {phone: phoneNumber}) }}
				<input v-model="confirmationCode">
				<button @click="confirm">
					{{ t('twofactor_gateway', 'Confirm') }}
				</button>
			</p>
			<p v-if="state === 3">
				{{ t('twofactor_gateway', 'Your account was successfully configured to receive messages via {displayName}.', {displayName: displayName}) }}
				<button @click="disable">
					{{ t('twofactor_gateway', 'Disable') }}
				</button>
			</p>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'GatewaySettings',
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
	data() {
		return {
			loading: true,
			state: 0,
			isAvailable: true,
			phoneNumber: '',
			confirmationCode: '',
			identifier: '',
			verificationError: false,
		}
	},
	mounted() {
		axios.get(generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification', { gateway: this.gatewayName }))
			.then(({ data }) => {
				console.debug('loaded state for gateway ' + this.gatewayName, data)
				this.isAvailable = true // data.isAvailable
				this.state = data.state
				this.phoneNumber = data.phoneNumber
			})
			.catch(err => console.info(this.gatewayName + ' gateway is not available', err))
			.then(() => {
				this.loading = false
			})
	},
	methods: {
		enable() {
			this.state = 1
			this.verificationError = false
			this.loading = false
		},
		verify() {
			this.loading = true
			this.verificationError = false
			axios.post(generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification/start', { gateway: this.gatewayName }), {
				identifier: this.identifier,
			})
				.then(res => {
					this.state = 2
					this.phoneNumber = res.phoneNumber
					this.loading = false
				})
				.catch(e => {
					console.error(e)
					this.state = 1
					this.verificationError = true
					this.loading = false
				})
		},
		confirm() {
			this.loading = true

			axios.post(generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification/finish', { gateway: this.gatewayName }), {
				verificationCode: this.confirmationCode,
			})
				.then(res => {
					this.state = 3
					this.loading = false
				})
				.catch(res => {
					this.state = 1
					this.verificationError = true
					this.loading = false
				})
		},

		disable() {
			this.loading = true
			axios.delete(generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification', { gateway: this.gatewayName }))
				.then(res => {
					this.state = res.state
					this.phoneNumber = res.phoneNumber
					this.loading = false
				})
				.catch(console.error.bind(this))
		},
	},
}
</script>

<style>
	.icon-loading-small {
		padding-left: 15px;
	}
</style>
