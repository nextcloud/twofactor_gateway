<template>
	<div class="section">
		<h2 data-anchor-name="sms-second-factor-auth">
			<L10n text="Message gateway second-factor auth"/>
		</h2>
		<L10n text="Here you can configure the message gateway to receive two-factor authentication codes via Signal, SMS or Telegram." />
		<GatewaySettings gateway-name="signal" display-name="Signal">
			<SignalInstructions slot="instructions" />
		</GatewaySettings>
		<GatewaySettings gateway-name="sms" display-name="SMS" />
		<GatewaySettings gateway-name="telegram" display-name="Telegram">
			<TelegramInstructions slot="instructions" />
		</GatewaySettings>
	</div>
</template>

<script>
	import L10n from "components/L10n.vue";
	import GatewaySettings from "../components/GatewaySettings.vue";
	import SignalInstructions from "../components/SignalInstructions.vue";
	import TelegramInstructions from "../components/TelegramInstructions.vue";

	export default {
		components: {
			TelegramInstructions,
			SignalInstructions,
			GatewaySettings,
			L10n
		}
	};
</script>
