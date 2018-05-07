<template>
    <div class="section">
        <h2 data-anchor-name="sms-second-factor-auth"><l10n text="SMS second-factor auth"></l10n></h2>
        <div v-if="loading">
              <span class="icon-loading-small"></span>
        </div>
        <div v-else>
          <p v-if="state === 0">
              <l10n text="You are not using SMS-based two-factor authentication at the moment"></l10n>
              <button @click="enable"><l10n text="Enable"></l10n></button>
          </p>
          <p v-if="state === 1">
              <l10n text="A confirmation code has been sent to {phone}. Please check your phone and insert the code here:"
                    v-bind:options="{phone: phoneNumber}"></l10n>
              <input v-model="confirmationCode">
              <button @click="confirm"><l10n text="Confirm"></l10n></button>
          </p>
          <p v-if="state === 2">
              <l10n text="SMS-based two-factor authentication is enabled for your account."></l10n>
          </p>
        </div>
    </div>
</template>

<script>
import l10n from "view/l10n.vue";
import { startVerification, tryVerification } from "service/registration";

export default {
  data() {
    return {
      loading: false,
      state: 0,
      phoneNumber: "12344556",
      confirmationCode: ""
    };
  },
  methods: {
    enable: function() {
      this.loading = true;
      startVerification().then(
        function() {
          this.state = 1;
          this.loading = false;
        }.bind(this)
      );
    },
    confirm: function() {
      this.loading = true;
      console.error(this.confirmationCode);

      tryVerification().then(
        function() {
          this.state = 2;
          this.loading = false;
        }.bind(this)
      );
    }
  },
  components: {
    l10n
  }
};
</script>

<style>
.icon-loading-small {
  padding-left: 15px;
}
</style>
