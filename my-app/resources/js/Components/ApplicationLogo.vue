<script setup>
defineProps({
    src: {
        type: String,
        default: '/images/logo-icon.png',
    },
});
</script>

<template>
    <img
        :src="src"
        alt="PremierSky"
        class="object-contain"
    >
</template>
