<template>
    <div class="section">
        <h2 data-anchor-name="sms-second-factor-auth"><l10n text="SMS second-factor auth"></l10n></h2>
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
</template>

<script>
import l10n from "./l10n.vue";

export default {
  data() {
    return {
      state: 0,
      phoneNumber: "12344556",
      confirmationCode: ""
    };
  },
  methods: {
    enable: function() {
      this.state = 1;
    },
    confirm: function() {
      console.error(this.confirmationCode);

      this.state = 2;
    }
  },
  components: {
    l10n
  }
};
</script>
