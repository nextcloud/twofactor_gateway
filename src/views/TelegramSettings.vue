<!--
  - SPDX-FileCopyrightText: 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<GatewaySettings gateway-name="telegram" display-name="Telegram">
		<TelegramInstructions slot="instructions" />
	</GatewaySettings>
</template>

<script>
import GatewaySettings from '../components/GatewaySettings.vue'
import TelegramInstructions from '../components/TelegramInstructions.vue'

export default {
	components: {
		TelegramInstructions,
		GatewaySettings,
	},
}
</script>
