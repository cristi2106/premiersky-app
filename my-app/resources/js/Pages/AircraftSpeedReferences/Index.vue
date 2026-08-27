<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryLinkButton from '@/Components/PrimaryLinkButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SwipeableListItem from '@/Components/SwipeableListItem.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    aircraftSpeedReferences: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const form = useForm({});
const aircraftSpeedReferencePendingDeletion = ref(null);
const search = ref(props.filters.search);

let searchDebounce = null;

watch(search, (value) => {
    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        router.get(
            route('aircraft-speed-references.index'),
            value ? { search: value } : {},
            { preserveState: true, preserveScroll: true, replace: true, only: ['aircraftSpeedReferences', 'filters'] }
        );
    }, 300);
});

const confirmDeletion = (aircraftSpeedReference) => {
    aircraftSpeedReferencePendingDeletion.value = aircraftSpeedReference;
};

const closeModal = () => {
    aircraftSpeedReferencePendingDeletion.value = null;
};

const deleteAircraftSpeedReference = () => {
    form.delete(route('aircraft-speed-references.destroy', aircraftSpeedReferencePendingDeletion.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Head title="Aircraft Types" />

    <AdminLayout title="Aircraft Types">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">
                {{ aircraftSpeedReferences.total }} aircraft type{{ aircraftSpeedReferences.total === 1 ? '' : 's' }}
            </p>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput
                    v-model="search"
                    placeholder="Search by aircraft type…"
                    class="sm:w-72"
                />

                <PrimaryLinkButton :href="route('aircraft-speed-references.create')">
                    New Aircraft Type
                </PrimaryLinkButton>
            </div>
        </div>

        <div v-if="aircraftSpeedReferences.data.length === 0" class="card mt-6">
            <div v-if="search" class="p-6 text-center text-sm text-gray-500">
                No aircraft types match "{{ search }}".
            </div>

            <EmptyState
                v-else
                title="No aircraft types yet"
                description="Aircraft types carry the cruise speed and cabin size the Flight Calculator, Tails and Contracts all rely on — add one, or run php artisan aircraft-speeds:import {path}."
            >
                <PrimaryLinkButton :href="route('aircraft-speed-references.create')">
                    New Aircraft Type
                </PrimaryLinkButton>
            </EmptyState>
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
                                Aircraft Type
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="aircraftSpeedReference in aircraftSpeedReferences.data"
                            :key="aircraftSpeedReference.id"
                            class="transition-colors duration-150 ease-in-out hover:bg-gray-50"
                        >
                            <td
                                class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900"
                            >
                                {{ aircraftSpeedReference.type_name }}
                            </td>
                            <td
                                class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium"
                            >
                                <Link
                                    :href="route('aircraft-speed-references.edit', aircraftSpeedReference.id)"
                                    class="text-accent-600 hover:text-accent-700"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    class="-my-1 ml-4 cursor-pointer rounded-lg px-2 py-1 text-red-600 transition duration-150 ease-in-out hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                    @click="confirmDeletion(aircraftSpeedReference)"
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
                <SwipeableListItem
                    v-for="aircraftSpeedReference in aircraftSpeedReferences.data"
                    :key="aircraftSpeedReference.id"
                    :delete-label="`Delete ${aircraftSpeedReference.type_name}`"
                    @delete="confirmDeletion(aircraftSpeedReference)"
                >
                    <p class="text-sm font-medium text-gray-900">
                        {{ aircraftSpeedReference.type_name }}
                    </p>

                    <div class="mt-4 flex justify-end text-sm font-medium">
                        <Link
                            :href="route('aircraft-speed-references.edit', aircraftSpeedReference.id)"
                            class="text-accent-600 hover:text-accent-700"
                        >
                            Edit
                        </Link>
                    </div>
                </SwipeableListItem>
            </div>
        </template>

        <Pagination :meta="aircraftSpeedReferences" class="mt-4" />

        <Modal :show="aircraftSpeedReferencePendingDeletion !== null" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Delete aircraft type?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Are you sure you want to delete
                    <span class="font-medium text-gray-900">{{
                        aircraftSpeedReferencePendingDeletion?.type_name || 'this aircraft type'
                    }}</span>? This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        Cancel
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :loading="form.processing"
                        @click="deleteAircraftSpeedReference"
                    >
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
