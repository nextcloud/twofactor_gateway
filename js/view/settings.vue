<template>
    <div class="section">
        <h2 data-anchor-name="sms-second-factor-auth"><l10n text="SMS second-factor auth"></l10n></h2>
        <div v-if="loading">
              <span class="icon-loading-small"></span>
        </div>
        <div v-else>
          <p v-if="state === 0">
              <strong v-if="verificationError === true"><l10n text="Could not verify your code. Please try again."></l10n></strong>
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
              <button @click="disable"><l10n text="Disable"></l10n></button>
          </p>
        </div>
    </div>
</template>

<script>
import l10n from "view/l10n.vue";
import {
  getState,
  startVerification,
  tryVerification,
  disable
} from "service/registration";

export default {
  data() {
    return {
      loading: true,
      state: 0,
      phoneNumber: "",
      confirmationCode: "",
      verificationError: false
    };
  },
  mounted: function() {
    getState()
      .then(res => {
        this.state = res.state;
        this.phoneNumber = res.phoneNumber;
        this.loading = false;
      })
      .catch(console.error.bind(this));
  },
  methods: {
    enable: function() {
      this.loading = true;
      this.verificationError = false;
      startVerification()
        .then(res => {
          this.state = 1;
          this.phoneNumber = res.phoneNumber;
          this.loading = false;
        })
        .catch(console.error.bind(this));
    },
    confirm: function() {
      this.loading = true;

      tryVerification(this.confirmationCode)
        .then(res => {
          this.state = 2;
          this.loading = false;
        })
        .catch(res => {
          this.state = 0;
          this.verificationError = true;
          this.loading = false;
        });
    },

    disable: function() {
      this.loading = true;

      disable()
        .then(res => {
          this.state = res.state;
          this.phoneNumber = res.phoneNumber;
          this.loading = false;
        })
        .catch(console.error.bind(this));
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
