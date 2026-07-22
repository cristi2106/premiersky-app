<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    airports: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const form = useForm({});
const airportPendingDeletion = ref(null);
const search = ref(props.filters.search);

let searchDebounce = null;

watch(search, (value) => {
    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        router.get(
            route('airports.index'),
            value ? { search: value } : {},
            { preserveState: true, preserveScroll: true, replace: true, only: ['airports', 'filters'] }
        );
    }, 300);
});

const confirmDeletion = (airport) => {
    airportPendingDeletion.value = airport;
};

const closeModal = () => {
    airportPendingDeletion.value = null;
};

const deleteAirport = () => {
    form.delete(route('airports.destroy', airportPendingDeletion.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Head title="Airports" />

    <AdminLayout title="Airports">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">
                {{ airports.total }} airport{{ airports.total === 1 ? '' : 's' }}
            </p>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput
                    v-model="search"
                    placeholder="Search by name, ICAO or IATA…"
                    class="sm:w-72"
                />

                <Link
                    :href="route('airports.create')"
                    class="inline-flex items-center justify-center rounded-lg border border-transparent bg-gray-900 px-4 py-2 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 active:bg-gray-950"
                >
                    New Airport
                </Link>
            </div>
        </div>

        <div v-if="airports.data.length === 0" class="card mt-6">
            <div class="p-6 text-center text-sm text-gray-500">
                <template v-if="search">
                    No airports match "{{ search }}".
                </template>
                <template v-else>
                    No airports yet. Get started by adding one, or run
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">php artisan airports:import</code>.
                </template>
            </div>
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div class="card mt-6 hidden overflow-hidden sm:block">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Name
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                ICAO
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                IATA
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Country
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="airport in airports.data"
                            :key="airport.id"
                            class="transition duration-100 ease-in-out hover:bg-gray-50"
                        >
                            <td
                                class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900"
                            >
                                {{ airport.name }}
                            </td>
                            <td
                                class="whitespace-nowrap px-6 py-4 text-sm text-gray-600"
                            >
                                {{ airport.icao_code }}
                            </td>
                            <td
                                class="whitespace-nowrap px-6 py-4 text-sm text-gray-600"
                            >
                                {{ airport.iata_code || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ airport.country }}
                            </td>
                            <td
                                class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium"
                            >
                                <Link
                                    :href="route('airports.edit', airport.id)"
                                    class="text-accent-600 hover:text-accent-700"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    class="ml-4 text-red-600 hover:text-red-700"
                                    @click="confirmDeletion(airport)"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile stacked cards -->
            <div class="mt-6 space-y-4 sm:hidden">
                <div
                    v-for="airport in airports.data"
                    :key="airport.id"
                    class="card p-4"
                >
                    <p class="text-sm font-medium text-gray-900">
                        {{ airport.name }}
                    </p>

                    <dl class="mt-2 space-y-1 text-sm text-gray-600">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">ICAO</dt>
                            <dd class="text-right">{{ airport.icao_code }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">IATA</dt>
                            <dd class="text-right">{{ airport.iata_code || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Country</dt>
                            <dd class="text-right">{{ airport.country }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex justify-end gap-4 text-sm font-medium">
                        <Link
                            :href="route('airports.edit', airport.id)"
                            class="text-accent-600 hover:text-accent-700"
                        >
                            Edit
                        </Link>
                        <button
                            type="button"
                            class="text-red-600 hover:text-red-700"
                            @click="confirmDeletion(airport)"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <Pagination :meta="airports" class="mt-4" />

        <Modal :show="airportPendingDeletion !== null" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Delete airport?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Are you sure you want to delete
                    <span class="font-medium text-gray-900">{{
                        airportPendingDeletion?.name || 'this airport'
                    }}</span>? This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        Cancel
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteAirport"
                    >
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
