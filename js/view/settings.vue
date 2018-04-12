<template>
    <div class="section">
        <h2 data-anchor-name="totp-second-factor-auth">SMS second-factor auth</h2>
        <p v-if="state === 0">
            You are not using SMS-based two-factor authentication at the moment.
            <button @click="enable">Enable</button>
        </p>
        <p v-if="state === 1">
            A confirmation code has been sent to …. Please check your phone and insert the code here: …
            <input v-model="confirmationCode">
            <button @click="confirm">Confirm</button>
        </p>
        <p v-if="state === 2">
            SMS-based two-factor authentication is enabled for your account.
        </p>
    </div>
</template>

<script>
export default {
  data() {
    return {
      state: 0,
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
  }
};
</script>
