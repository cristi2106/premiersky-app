<script setup>
defineProps({
    padding: {
        type: String,
        default: 'p-6',
    },
});
</script>

<template>
    <div class="card" :class="padding">
        <slot />
    </div>
</template>
