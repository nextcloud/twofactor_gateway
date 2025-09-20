<?php
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

\OCP\Util::addStyle('twofactor_gateway', 'twofactor_gateway-main');
\OCP\Util::addScript('twofactor_gateway', 'twofactor_gateway-main');
?>
<input type="hidden" id="twofactor-gateway-<?php print_unescaped($_['gateway']) ?>-is-complete" value="<?php echo $_['isComplete'] ? 1: 0; ?>">
<div id="twofactor-gateway-<?php print_unescaped($_['gateway']) ?>"></div>
