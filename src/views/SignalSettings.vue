<!--
  - SPDX-FileCopyrightText: 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<GatewaySettings gateway-name="signal" display-name="Signal">
		<template #instructions>
			<SignalInstructions />
		</template>
	</GatewaySettings>
</template>

<script>
import GatewaySettings from '../components/GatewaySettings.vue'
import SignalInstructions from '../components/SignalInstructions.vue'

export default {
	components: {
		SignalInstructions,
		GatewaySettings,
	},
}
</script>
