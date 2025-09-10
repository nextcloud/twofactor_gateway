<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<p>
			In order to receive authentication codes via Telegram, you first
			have to start a new chat with the bot set up by your admin.<br>
			Secondly, you have to obtain your Telegram ID via the <a href="https://telegram.me/getmyid_bot"
				target="_blank"
				rel="noreferrer noopener">ID Bot</a>.
			Enter this ID to receive your verification code below.
		</p>
	</div>
</template>

<script>
export default {
	name: 'TelegramInstructions',
}
</script>
