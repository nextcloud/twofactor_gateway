<template>
	<span>{{ translated }}</span>
</template>

<script>
export default {
	props: ['text', 'options'],
	computed: {
		translated() {
			return t('twofactor_gateway', this.text, this.options || {})
		},
	},
}
</script>
