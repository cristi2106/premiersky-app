<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout title="Dashboard">
        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <div class="p-6 text-gray-900">You're logged in!</div>
        </div>
    </AdminLayout>
</template>
