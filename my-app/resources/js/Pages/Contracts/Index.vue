<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Badge.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryLinkButton from '@/Components/PrimaryLinkButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SwipeableListItem from '@/Components/SwipeableListItem.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    contracts: {
        type: Object,
        required: true,
    },
});

const form = useForm({});
const contractPendingDeletion = ref(null);

const statusVariant = (status) =>
    ({ draft: 'neutral', confirmed: 'info', completed: 'success' })[status] ?? 'neutral';

const routeLabel = (contract) => {
    if (!contract.departure_icao || !contract.arrival_icao) {
        return '—';
    }

    const base = `${contract.departure_icao} → ${contract.arrival_icao}`;

    return contract.extra_legs > 0 ? `${base} (+${contract.extra_legs} leg${contract.extra_legs === 1 ? '' : 's'})` : base;
};

const confirmDeletion = (contract) => {
    contractPendingDeletion.value = contract;
};

const closeModal = () => {
    contractPendingDeletion.value = null;
};

const deleteContract = () => {
    form.delete(route('contracts.destroy', contractPendingDeletion.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Head title="Contracts" />

    <AdminLayout title="Contracts">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">
                {{ contracts.total }} contract{{ contracts.total === 1 ? '' : 's' }}
            </p>

            <PrimaryLinkButton :href="route('contracts.create')">
                New Contract
            </PrimaryLinkButton>
        </div>

        <div v-if="contracts.data.length === 0" class="card mt-6">
            <div class="p-6 text-center text-sm text-gray-500">
                No contracts yet. Get started by creating one.
            </div>
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div class="card mt-6 hidden overflow-hidden sm:block">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Client
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Route
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Flight Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Status
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="contract in contracts.data"
                            :key="contract.id"
                            class="transition duration-100 ease-in-out hover:bg-gray-50"
                        >
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                {{ contract.client_name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                {{ routeLabel(contract) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                {{ contract.flight_date || '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <Badge :variant="statusVariant(contract.status)">{{ contract.status }}</Badge>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <a
                                    :href="route('contracts.pdf', contract.id)"
                                    target="_blank"
                                    class="text-accent-600 hover:text-accent-700"
                                >
                                    PDF
                                </a>
                                <Link
                                    :href="route('contracts.edit', contract.id)"
                                    class="ml-4 text-accent-600 hover:text-accent-700"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    class="-my-1 ml-4 cursor-pointer rounded-md px-2 py-1 text-red-600 transition duration-150 ease-in-out hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                    @click="confirmDeletion(contract)"
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
                    v-for="contract in contracts.data"
                    :key="contract.id"
                    :delete-label="`Delete contract for ${contract.client_name}`"
                    @delete="confirmDeletion(contract)"
                >
                    <div class="flex items-start justify-between gap-4">
                        <p class="text-sm font-medium text-gray-900">
                            {{ contract.client_name }}
                        </p>
                        <Badge :variant="statusVariant(contract.status)">{{ contract.status }}</Badge>
                    </div>

                    <dl class="mt-2 space-y-1 text-sm text-gray-600">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Route</dt>
                            <dd class="text-right">{{ routeLabel(contract) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Flight Date</dt>
                            <dd class="text-right">{{ contract.flight_date || '—' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex justify-end gap-4 text-sm font-medium">
                        <a :href="route('contracts.pdf', contract.id)" target="_blank" class="text-accent-600 hover:text-accent-700">
                            PDF
                        </a>
                        <Link :href="route('contracts.edit', contract.id)" class="text-accent-600 hover:text-accent-700">
                            Edit
                        </Link>
                    </div>
                </SwipeableListItem>
            </div>
        </template>

        <Pagination :meta="contracts" class="mt-4" />

        <Modal :show="contractPendingDeletion !== null" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Delete contract?</h2>

                <p class="mt-1 text-sm text-gray-600">
                    Are you sure you want to delete the contract for
                    <span class="font-medium text-gray-900">{{
                        contractPendingDeletion?.client_name || 'this client'
                    }}</span>? This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">Cancel</SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteContract"
                    >
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
