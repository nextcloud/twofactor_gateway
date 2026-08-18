<?php
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

\OCP\Util::addStyle('twofactor_gateway', 'twofactor_gateway-login-setup');
\OCP\Util::addScript('twofactor_gateway', 'twofactor_gateway-login-setup');
?>
<input type="hidden" id="twofactor-gateway-login-setup-gateway" value="<?php print_unescaped($_['gateway']) ?>">
<input type="hidden" id="twofactor-gateway-login-setup-is-complete" value="<?php echo $_['isComplete'] ? 1: 0; ?>">
<div id="twofactor-gateway-login-setup"></div>
