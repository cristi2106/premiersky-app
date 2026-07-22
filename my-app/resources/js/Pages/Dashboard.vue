<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Card.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

// Add new modules here as they're built out. `route` links straight to the
// module; omit it to render the card as a muted "coming soon" placeholder.
const modules = [
    { name: 'Clients', description: 'Company records and billing details.', route: 'clients.index' },
    { name: 'Aircraft', description: 'Fleet and aircraft details.' },
    { name: 'Contracts', description: 'Charter contracts and status.' },
    { name: 'Quotes', description: 'Client quotes and offers.' },
];
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout title="Dashboard">
        <h2 class="text-lg font-semibold tracking-tight text-gray-900">
            Welcome back, {{ page.props.auth.user.name }}
        </h2>
        <p class="mt-1 text-sm text-gray-500">
            Here's a quick look at your workspace.
        </p>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <component
                :is="module.route ? Link : 'div'"
                v-for="module in modules"
                :key="module.name"
                :href="module.route ? route(module.route) : undefined"
                class="card block p-5 transition duration-150 ease-in-out"
                :class="
                    module.route
                        ? 'hover:border-gray-300 hover:shadow-sm'
                        : 'opacity-60'
                "
            >
                <p class="text-sm font-semibold text-gray-900">
                    {{ module.name }}
                </p>
                <p class="mt-1 text-sm text-gray-500">
                    {{ module.description }}
                </p>
                <p
                    v-if="!module.route"
                    class="mt-3 badge badge-neutral"
                >
                    Coming soon
                </p>
            </component>
        </div>
    </AdminLayout>
</template>
