<script setup>
defineProps({
    variant: {
        type: String,
        default: 'neutral',
        validator: (value) =>
            ['success', 'warning', 'danger', 'info', 'neutral'].includes(
                value,
            ),
    },
});
</script>

<template>
    <span class="badge" :class="`badge-${variant}`">
        <slot />
    </span>
</template>
