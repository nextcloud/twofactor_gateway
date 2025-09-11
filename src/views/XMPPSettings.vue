<!--
  - SPDX-FileCopyrightText: 2023 Rainer Dohmen <rdohmen@pensionmoselblick.de>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<GatewaySettings gateway-name="xmpp" display-name="XMPP">
		<template #instructions>
			<XMPPInstructions />
		</template>
	</GatewaySettings>
</template>

<script>
import GatewaySettings from '../components/GatewaySettings.vue'
import XMPPInstructions from '../components/XMPPInstructions.vue'

export default {
	components: {
		XMPPInstructions,
		GatewaySettings,
	},
}
</script>
