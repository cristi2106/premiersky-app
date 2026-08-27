<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

// Every module the app actually has today (see PRODUCT.md's "Evidence on
// Hand") — kept in sync by hand with AdminLayout's sidebar list, since
// this card grid is the same set of destinations in a different shape.
// Charter Fleet Directory is real and linked here too even though it
// isn't one of the sidebar's primary items.
const modules = [
    { name: 'Clients', description: 'Company records and billing details.', route: 'clients.index' },
    { name: 'Airports', description: 'Reference data used by the flight calculator and contracts.', route: 'airports.index' },
    { name: 'Aircraft Types', description: 'Cruise speed, cabin size and seating by aircraft type.', route: 'aircraft-speed-references.index' },
    { name: 'Flight Calculator', description: 'Distance, flight time and local arrival for any route.', route: 'flight-calculator.index' },
    { name: 'Tails', description: 'Charter-ready aircraft on file, with photos and amenities.', route: 'tails.index' },
    { name: 'Charter Fleet Directory', description: 'Operator aircraft available for charter, synced from Aviapages.', route: 'charter-fleet.index' },
    { name: 'Quotes', description: 'Pull operator offers from email and compare them.', route: 'quotes.index' },
    { name: 'Contracts', description: 'Charter contracts, pricing and status.', route: 'contracts.index' },
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
            <Link
                v-for="module in modules"
                :key="module.name"
                :href="route(module.route)"
                class="card card-hover block p-5"
            >
                <p class="text-sm font-semibold text-gray-900">
                    {{ module.name }}
                </p>
                <p class="mt-1 text-sm text-gray-500">
                    {{ module.description }}
                </p>
            </Link>
        </div>
    </AdminLayout>
</template>
