
<fieldset class="warning">
	<span><?php p($l->t('An access code has been sent to') . ' ' . $_['phone']); ?></span>
</fieldset>

<?php if (isset($_['secret'])): ?>
	<p>TOTP: <?php p($_['secret']); ?></p>
<?php endif; ?>

<form method="POST">
	<input type="hidden" name="redirect_url" value="<?php p($_['redirect_url']); ?>">
	<input type="text" name="challenge" autocomplete="off" autocapitalize="off" required="required">
	<input type="submit" class="button" value="Verify">
</form>
