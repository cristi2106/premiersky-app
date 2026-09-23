<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { MapPin, Plane, Tag, Users } from 'lucide-vue-next';

// Kept in sync by hand with AdminLayout's "Database" sidebar entry, which
// groups these same four modules — see the note there. Icons match the
// ones already used for Clients/Tails on the mobile bottom nav (Airports
// and Aircraft Types have no nav icon of their own to match, since they're
// only ever reached through this hub).
const modules = [
    { name: 'Clients', description: 'Company records and billing details.', route: 'clients.index', icon: Users },
    { name: 'Airports', description: 'Reference data used by the flight calculator and contracts.', route: 'airports.index', icon: MapPin },
    { name: 'Aircraft Types', description: 'Cruise speed, cabin size and seating by aircraft type.', route: 'aircraft-speed-references.index', icon: Plane },
    { name: 'Tails', description: 'Charter-ready aircraft on file, with photos and amenities.', route: 'tails.index', icon: Tag },
];
</script>

<template>
    <Head title="Database" />

    <AdminLayout title="Database">
        <p class="text-sm text-gray-500">
            Reference records shared across quotes and contracts.
        </p>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                v-for="module in modules"
                :key="module.name"
                :href="route(module.route)"
                class="card card-hover block p-5"
            >
                <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-accent-50 text-accent-600">
                    <component :is="module.icon" class="h-6 w-6" />
                </div>

                <p class="mt-3 text-sm font-semibold text-gray-900">
                    {{ module.name }}
                </p>
                <p class="mt-1 text-sm text-gray-500">
                    {{ module.description }}
                </p>
            </Link>
        </div>
    </AdminLayout>
</template>
