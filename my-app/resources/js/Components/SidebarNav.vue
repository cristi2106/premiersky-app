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
    <nav class="flex-1 space-y-1 px-3 py-4">
        <Link
            v-for="item in navigation"
            :key="item.name"
            :href="route(item.route)"
            @click="$emit('navigate')"
            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition duration-150 ease-in-out"
            :class="
                route().current(item.route)
                    ? 'bg-slate-800 text-white'
                    : 'text-slate-300 hover:bg-slate-800 hover:text-white'
            "
        >
            <svg
                class="h-5 w-5 shrink-0"
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
