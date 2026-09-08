<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    navigation: {
        type: Array,
        required: true,
    },
});

defineEmits(['navigate']);
</script>

<template>
    <!-- lg: bumps below apply only to the desktop sidebar (this same
         component also fills the mobile drawer, which is lg:hidden, so
         the lg-prefixed sizes never reach it). -->
    <nav class="flex-1 space-y-0.5 px-3 py-4 lg:space-y-1">
        <Link
            v-for="item in navigation"
            :key="item.name"
            :href="route(item.route)"
            @click="$emit('navigate')"
            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition duration-150 ease-in-out lg:gap-3.5 lg:px-3.5 lg:py-2.5 lg:text-base"
            :class="
                route().current(item.active ?? item.route)
                    ? 'bg-accent-600 text-white'
                    : 'text-gray-400 hover:bg-white/5 hover:text-white'
            "
        >
            <svg
                class="h-5 w-5 shrink-0 lg:h-6 lg:w-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    :d="item.icon"
                />
            </svg>
            {{ item.name }}
        </Link>
    </nav>
</template>
