/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'

import SignalSettings from './views/SignalSettings.vue'
import SMSSettings from './views/SMSSettings.vue'
import TelegramSettings from './views/TelegramSettings.vue'
import XMPPSettings from './views/XMPPSettings.vue'

createApp(SignalSettings).mount('#twofactor-gateway-signal')
createApp(SMSSettings).mount('#twofactor-gateway-sms')
createApp(TelegramSettings).mount('#twofactor-gateway-telegram')
createApp(XMPPSettings).mount('#twofactor-gateway-xmpp')
