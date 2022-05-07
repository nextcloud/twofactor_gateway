<template>
	<div>
		<div v-if="!isAvailable">
			<L10n text="The {displayName} gateway is not configured."
				  :options="{displayName: displayName}"/>
		</div>
		<div v-else-if="loading">
			<span class="icon-loading-small"></span>
		</div>
		<div v-else>
			<p v-if="state === 0">
				<slot name="instructions"/>
				<L10n text="You are not using {displayName} for two-factor authentication at the moment."
					  :options="{displayName: displayName}"/>
				<button @click="enable">
					<L10n text="Enable"></L10n>
				</button>
			</p>
			<p v-if="state === 1">
				<slot name="instructions"/>
				<strong v-if="verificationError === true">
					<L10n text="Could not verify your code. Please try again."></L10n>
				</strong>
				<L10n text="Enter your identification (e.g. phone number to start the verification):"></L10n>
				<input v-model="identifier">
				<button @click="verify">
					<L10n text="Verify"></L10n>
				</button>
			</p>
			<p v-if="state === 2">
				<L10n text="A confirmation code has been sent to {phone} via SMS. Please insert the code here:"
					  :options="{phone: phoneNumber}"></L10n>
				<input v-model="confirmationCode">
				<button @click="confirm">
					<L10n text="Confirm"></L10n>
				</button>
			</p>
			<p v-if="state === 3">
				<L10n text="Your account was successfully configured to receive messages via {displayName}."
					  :options="{displayName: displayName}"/>
				<button @click="disable">
					<l10n text="Disable"></l10n>
				</button>
			</p>
		</div>
	</div>
</template>

<script>
	import L10n from "./L10n.vue";
	import {
		getState,
		startVerification,
		tryVerification,
		disable
	} from "../service/registration";

	export default {
		name: "GatewaySettings",
		props: [
			'gatewayName',
			'displayName',
		],
		data () {
			return {
				loading: true,
				state: 0,
				isAvailable: true,
				phoneNumber: '',
				confirmationCode: '',
				identifier: '',
				verificationError: false
			};
		},
		mounted: function () {
			getState(this.gatewayName)
				.then(res => {
					console.debug('loaded state for gateway ' + this.gatewayName, res);
					this.isAvailable = res.isAvailable;
					this.state = res.state;
					this.phoneNumber = res.phoneNumber;
				})
				.catch(console.error.bind(this))
				.then(() => this.loading = false);
		},
		methods: {
			enable: function () {
				this.state = 1;
				this.verificationError = false;
				this.loading = false;
			},
			verify: function () {
				this.loading = true;
				this.verificationError = false;
				startVerification(this.gatewayName, this.identifier)
					.then(res => {
						this.state = 2;
						this.phoneNumber = res.phoneNumber;
						this.loading = false;
					})
					.catch(e => {
						console.error(e);
						this.state = 1;
						this.verificationError = true;
						this.loading = false;
					});
			},
			confirm: function () {
				this.loading = true;

				tryVerification(this.gatewayName, this.confirmationCode)
					.then(res => {
						this.state = 3;
						this.loading = false;
					})
					.catch(res => {
						this.state = 1;
						this.verificationError = true;
						this.loading = false;
					});
			},

			disable: function () {
				this.loading = true;

				disable(this.gatewayName)
					.then(res => {
						this.state = res.state;
						this.phoneNumber = res.phoneNumber;
						this.loading = false;
					})
					.catch(console.error.bind(this));
			}
		},
		components: {
			L10n
		}
	}
</script>

<style>
	.icon-loading-small {
		padding-left: 15px;
	}
</style>
