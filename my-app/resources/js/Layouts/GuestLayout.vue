<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-10 sm:px-6"
    >
        <Link href="/login" class="flex items-center gap-3">
            <ApplicationLogo class="h-10 w-10" />
            <span class="text-xl font-semibold tracking-tight text-gray-900">
                PremierSky
            </span>
        </Link>

        <div
            class="mt-8 w-full rounded-xl border border-gray-200 bg-white px-6 py-8 sm:max-w-md sm:px-8"
        >
            <slot />
        </div>
    </div>
</template>
