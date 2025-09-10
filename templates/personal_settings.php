<?php
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

\OCP\Util::addScript('twofactor_gateway', 'build');
?>

<div id="twofactor-gateway-<?php print_unescaped($_['gateway']) ?>"></div>
