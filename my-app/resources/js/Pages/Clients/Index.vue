<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    clients: {
        type: Array,
        required: true,
    },
});

const form = useForm({});
const clientPendingDeletion = ref(null);

const confirmDeletion = (client) => {
    clientPendingDeletion.value = client;
};

const closeModal = () => {
    clientPendingDeletion.value = null;
};

const deleteClient = () => {
    form.delete(route('clients.destroy', clientPendingDeletion.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Head title="Clients" />

    <AdminLayout title="Clients">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-600">
                {{ clients.length }} client{{ clients.length === 1 ? '' : 's' }}
            </p>

            <Link
                :href="route('clients.create')"
                class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
            >
                New Client
            </Link>
        </div>

        <div
            v-if="clients.length === 0"
            class="mt-6 overflow-hidden rounded-lg bg-white shadow-sm"
        >
            <div class="p-6 text-center text-gray-500">
                No clients yet. Get started by creating one.
            </div>
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div
                class="mt-6 hidden overflow-hidden rounded-lg bg-white shadow-sm sm:block"
            >
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Company Name
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Address
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                VAT Code
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                IBAN
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Bank Name
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="client in clients" :key="client.id">
                            <td
                                class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900"
                            >
                                {{ client.company_name || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ client.address || '—' }}
                            </td>
                            <td
                                class="whitespace-nowrap px-6 py-4 text-sm text-gray-600"
                            >
                                {{ client.vat_code || '—' }}
                            </td>
                            <td
                                class="whitespace-nowrap px-6 py-4 text-sm text-gray-600"
                            >
                                {{ client.iban || '—' }}
                            </td>
                            <td
                                class="whitespace-nowrap px-6 py-4 text-sm text-gray-600"
                            >
                                {{ client.bank_name || '—' }}
                            </td>
                            <td
                                class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium"
                            >
                                <Link
                                    :href="route('clients.edit', client.id)"
                                    class="text-indigo-600 hover:text-indigo-900"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    class="ml-4 text-red-600 hover:text-red-900"
                                    @click="confirmDeletion(client)"
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
                    v-for="client in clients"
                    :key="client.id"
                    class="overflow-hidden rounded-lg bg-white p-4 shadow-sm"
                >
                    <p class="text-sm font-medium text-gray-900">
                        {{ client.company_name || 'Untitled client' }}
                    </p>

                    <dl class="mt-2 space-y-1 text-sm text-gray-600">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Address</dt>
                            <dd class="text-right">{{ client.address || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">VAT Code</dt>
                            <dd class="text-right">{{ client.vat_code || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">IBAN</dt>
                            <dd class="text-right">{{ client.iban || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Bank Name</dt>
                            <dd class="text-right">{{ client.bank_name || '—' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex justify-end gap-4 text-sm font-medium">
                        <Link
                            :href="route('clients.edit', client.id)"
                            class="text-indigo-600 hover:text-indigo-900"
                        >
                            Edit
                        </Link>
                        <button
                            type="button"
                            class="text-red-600 hover:text-red-900"
                            @click="confirmDeletion(client)"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <Modal :show="clientPendingDeletion !== null" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Delete client?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Are you sure you want to delete
                    <span class="font-medium text-gray-900">{{
                        clientPendingDeletion?.company_name || 'this client'
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
                        @click="deleteClient"
                    >
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
