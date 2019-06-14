import Vue from "vue"

import SignalSettings from "views/SignalSettings.vue"
import SMSSettings from "views/SMSSettings.vue"
import TelegramSettings from "views/TelegramSettings.vue"
import EmailSettings from "views/EmailSettings.vue"

Vue.config.productionTip = false

new Vue({
    render: h => h(SignalSettings)
}).$mount('#twofactor-gateway-signal')

new Vue({
    render: h => h(SMSSettings)
}).$mount('#twofactor-gateway-sms')

new Vue({
    render: h => h(TelegramSettings)
}).$mount('#twofactor-gateway-telegram')

new Vue({
    render: h => h(EmailSettings)
}).$mount('#twofactor-gateway-email')
