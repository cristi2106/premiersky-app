<script setup>
// Shared "nothing here yet" state for any list/table that can be empty —
// an icon, a short message, and (via the default slot) a call-to-action,
// so an empty module reads as "here's how to get started" rather than a
// blank card or a bare "no results" sentence. The search-no-matches case
// each Index page also has is deliberately NOT this component — that's a
// much lower-stakes moment (try a different search) that doesn't need an
// icon or a CTA, just a line of text; see each page's own template.
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="flex flex-col items-center px-6 py-12 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
            <!-- Default icon: a generic empty tray. Callers with a more
                 specific glyph (an airport pin, a plane) pass their own
                 via the #icon slot instead. -->
            <slot name="icon">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5l1.5 -3h15l1.5 3m-18 0v10.5a1.5 1.5 0 001.5 1.5h15a1.5 1.5 0 001.5-1.5V7.5m-18 0h18M8.25 12h7.5" />
                </svg>
            </slot>
        </div>

        <p class="mt-4 text-sm font-medium text-gray-900">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-gray-500">{{ description }}</p>

        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
