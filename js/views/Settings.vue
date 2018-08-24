<template>
	<div class="section">
		<h2 data-anchor-name="sms-second-factor-auth">
			<L10n text="Message gateway second-factor auth"/>
		</h2>
		<L10n text="Here you can configure the message gateway to receive two-factor authentication codes via Signal, SMS or Telegram." />
		<GatewaySettings gateway-name="signal" display-name="Signal" />
		<GatewaySettings gateway-name="sms" display-name="SMS" />
		<GatewaySettings gateway-name="telegram" display-name="Telegram" />
	</div>
</template>

<script>
	import L10n from "components/L10n.vue";
	import GatewaySettings from "../components/GatewaySettings.vue";

	export default {
		components: {
			GatewaySettings,
			L10n
		}
	};
</script>

<style>
	.icon-loading-small {
		padding-left: 15px;
	}
</style>
