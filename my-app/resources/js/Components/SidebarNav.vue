<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    navigation: {
        type: Array,
        required: true,
    },
});

defineEmits(['navigate']);

// `item.active` may be a single Ziggy wildcard pattern or an array of them
// (e.g. the "Database" entry, which should light up for any of the four
// modules it groups) — Ziggy's route().current() only matches one pattern
// per call, so multiple patterns are OR'd together here.
const isActive = (item) => {
    const patterns = item.active ?? item.route;

    return [].concat(patterns).some((pattern) => route().current(pattern));
};
</script>

<template>
    <!-- Unprefixed sizes here are bigger than the lg: desktop ones on
         purpose — this component fills both the mobile drawer (lg:hidden,
         so it never sees the lg-prefixed sizes) and the desktop sidebar,
         and the drawer needs the larger touch targets a mouse doesn't. -->
    <nav class="flex-1 space-y-1.5 px-4 py-5 lg:space-y-1 lg:px-3 lg:py-4">
        <Link
            v-for="item in navigation"
            :key="item.name"
            :href="route(item.route)"
            @click="$emit('navigate')"
            class="flex items-center gap-4 rounded-lg px-4 py-3.5 text-lg font-bold text-white transition duration-150 ease-in-out lg:gap-3.5 lg:px-3.5 lg:py-2.5 lg:text-base"
            :class="isActive(item) ? 'bg-accent-600' : 'hover:bg-white/5'"
        >
            <component :is="item.icon" class="h-7 w-7 shrink-0 lg:h-6 lg:w-6" />
            {{ item.name }}
        </Link>
    </nav>
</template>
