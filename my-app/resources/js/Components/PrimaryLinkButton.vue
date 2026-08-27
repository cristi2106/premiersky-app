<script setup>
import { Link } from '@inertiajs/vue3';

// Link-flavored sibling of PrimaryButton — same visual treatment, but for
// navigations ("New Client", "New Contract", …) rather than form submits.
// Keeping the two in sync by hand was the actual drift this fixed: every
// list page had re-typed PrimaryButton's class string onto a raw <Link>.
defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition duration-150 ease-in-out hover:bg-gray-800 hover:shadow focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 active:scale-[0.98] active:bg-gray-950"
    >
        <slot />
    </Link>
</template>
