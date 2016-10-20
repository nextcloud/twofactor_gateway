<?php
style('twofactor_sms', 'style');
?>

<form method="POST" class="sms-2fa-form">
	<input type="text"
		   class="challenge"
		   name="challenge"
		   required="required"
		   autofocus
		   autocomplete="off"
		   autocapitalize="off"
		   value="<?php echo isset($_['secret']) ? $_['secret'] : '' ?>"
		   placeholder="<?php p($l->t('Authentication code')) ?>">
	<input type="submit" class="confirm-inline icon-confirm" value="">
	<p><?php p($l->t('An access code has been sent to %s', [$_['phone']])); ?></p>
</form>
