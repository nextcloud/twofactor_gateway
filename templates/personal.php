<?php
script('twofactor_sms', 'settings');
?>

<div class="section" id="twofactor-sms">
    <h2><?php p($l->t('Second-factor SMS')); ?></h2>
    <div>
      <label for="phone-number"><?php p($l->t('Phone number for SMS codes')) ?></label>
      <input type="text" id="phone-number" value="<?php p($_['phone']) ?>" /><span class="msg"></span>
    </div>
</div>
