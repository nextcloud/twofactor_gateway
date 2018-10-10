<template>
	<div>
		<p>
			In order to receive authentication codes via Telegram, your admin needs
			to setup a Telegram Bot that will send the authentication codes to you.
			Once this has been done:<br>
			<ol>
			  <li>
					Start a conversation with the Bot. You can ask your admin for
					the bot name.
				</li>
				<li>
					You need your Chat Id. You can get it sending a message to the <a
						href="https://telegram.me/get_id_bot"
						target="_blank"
						rel="noreferrer noopener">ID Bot</a>.
				</li>
				<li>
					Introduce your Chat Id in the following box. You will receive a
					confirmation message from the bot.
				</li>
			</ol>
		</p>
	</div>
</template>

<script>
	export default {
		name: "TelegramInstructions"
	}
</script>
