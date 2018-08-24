import Vue from "vue"

import SettingsView from "views/Settings.vue"

Vue.config.productionTip = false

new Vue({
    render: h => h(SettingsView)
}).$mount('#twofactor-sms-section')
