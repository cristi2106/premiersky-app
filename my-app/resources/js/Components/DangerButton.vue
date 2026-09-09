<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
    // See PrimaryButton's own doc comment on loading vs. disabled.
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center gap-2 rounded-lg border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition duration-150 ease-in-out hover:bg-red-500 hover:shadow focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 active:scale-[0.98] active:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100"
    >
        <svg
            v-if="loading"
            class="h-4 w-4 animate-spin text-white/80"
            viewBox="0 0 24 24"
            fill="none"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>
        <slot />
    </button>
</template>
