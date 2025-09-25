<?php

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

\OCP\Util::addStyle('twofactor_gateway', 'login');
?>

<img class="two-factor-icon two-factor-gateway-icon" src="<?php print_unescaped(image_path('twofactor_gateway', 'app.svg')); ?>" alt="">

<form method="POST" class="twofactor_gateway-form">
	<input type="text"
		   minlength="6"
		   maxlength="10"
		   class="challenge"
		   name="challenge"
		   required="required"
		   autofocus
		   autocomplete="off"
		   inputmode="numeric"
		   autocapitalize="off"
		   value="<?php echo isset($_['secret']) ? $_['secret'] : '' ?>"
		   placeholder="<?php p($l->t('Authentication code')) ?>">
	<button class="primary two-factor-submit" type="submit">
		<?php p($l->t('Submit')); ?>
	</button>
	<p><?php p($l->t('An access code has been sent to %s', [$_['phone']])); ?></p>
</form>
