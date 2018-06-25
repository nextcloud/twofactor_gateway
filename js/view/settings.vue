<template>
	<div class="section">
		<h2 data-anchor-name="sms-second-factor-auth">
			<l10n text="Message gateway second-factor auth"></l10n>
		</h2>
		<div v-if="loading">
			<span class="icon-loading-small"></span>
		</div>
		<div v-else>
			<p>
				<l10n text="Here you can configure the message gateway to receive two-factor authentication codes via {gateway}."
					  v-bind:options="{gateway: gatewayName}"></l10n>
			</p>
			<p v-if="state === 0">
				<l10n text="You are not using gateway for two-factor authentication at the moment."></l10n>
				<button @click="enable">
					<l10n text="Enable"></l10n>
				</button>
			</p>
			<p v-if="state === 1">
				<strong v-if="verificationError === true">
					<l10n text="Could not verify your code. Please try again."></l10n>
				</strong>
				<l10n text="Enter your identification (e.g. phone number to start the verification):"></l10n>
				<input v-model="identifier">
				<button @click="verify">
					<l10n text="Verify"></l10n>
				</button>
			</p>
			<p v-if="state === 2">
				<l10n text="A confirmation code has been sent to {phone} via {gateway}. Please insert the code here:"
					  v-bind:options="{gateway: gatewayName, phone: phoneNumber}"></l10n>
				<input v-model="confirmationCode">
				<button @click="confirm">
					<l10n text="Confirm"></l10n>
				</button>
			</p>
			<p v-if="state === 3">
				<l10n text="Your account was successfully configured to receive messages via {gateway}."
					  v-bind:options="{gateway: gatewayName}"></l10n>
				<button @click="disable">
					<l10n text="Disable"></l10n>
				</button>
			</p>
		</div>
	</div>
</template>

<script>
	import l10n from "view/l10n.vue";
	import {
		getState,
		startVerification,
		tryVerification,
		disable
	} from "service/registration";

	export default {
		data () {
			return {
				loading: true,
				state: 0,
				phoneNumber: '',
				confirmationCode: '',
				identifier: '',
				gatewayName: '',
				verificationError: false
			};
		},
		mounted: function () {
			getState()
				.then(res => {
					this.gatewayName = res.gatewayName;
					this.state = res.state;
					this.phoneNumber = res.phoneNumber;
					this.loading = false;
				})
				.catch(console.error.bind(this));
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
				startVerification(this.identifier)
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

				tryVerification(this.confirmationCode)
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

				disable()
					.then(res => {
						this.state = res.state;
						this.phoneNumber = res.phoneNumber;
						this.loading = false;
					})
					.catch(console.error.bind(this));
			}
		},
		components: {
			l10n
		}
	};
</script>

<style>
	.icon-loading-small {
		padding-left: 15px;
	}
</style>
