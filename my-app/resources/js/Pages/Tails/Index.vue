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
import { TAIL_AMENITIES } from '@/tailAmenities';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    tails: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const form = useForm({});
const search = ref(props.filters.search);
const selectedTail = ref(null);
const tailPendingDeletion = ref(null);

let searchDebounce = null;

watch(search, (value) => {
    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        router.get(
            route('tails.index'),
            value ? { search: value } : {},
            { preserveState: true, preserveScroll: true, replace: true, only: ['tails', 'filters'] }
        );
    }, 300);
});

const openDetails = (tail) => {
    selectedTail.value = tail;
};

const closeDetails = () => {
    selectedTail.value = null;
};

const confirmDeletion = (tail) => {
    tailPendingDeletion.value = tail;
};

const closeDeletion = () => {
    tailPendingDeletion.value = null;
};

const deleteTail = () => {
    form.delete(route('tails.destroy', tailPendingDeletion.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeletion(),
    });
};

const activeAmenities = computed(() => {
    const tail = selectedTail.value;

    if (!tail) {
        return [];
    }

    return TAIL_AMENITIES.filter(([key]) => tail[key]).map(([, label]) => label);
});

const cabinFields = computed(() => {
    const type = selectedTail.value?.aircraft_speed_reference;

    if (!type) {
        return [];
    }

    return [
        ['Cabin width (m)', type.cabin_width_m],
        ['Cabin height (m)', type.cabin_height_m],
        ['Cabin length (m)', type.cabin_length_m],
        ['Cabin volume (m3)', type.cabin_volume_m3],
        ['Baggage capacity (m3)', type.baggage_capacity_m3],
        ['Seating capacity', type.seating_capacity],
    ].filter(([, value]) => value !== null && value !== undefined && value !== '');
});
</script>

<template>
    <Head title="Tails" />

    <AdminLayout title="Tails">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">
                {{ tails.total }} tail{{ tails.total === 1 ? '' : 's' }}
            </p>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput
                    v-model="search"
                    placeholder="Search by tail number or operator…"
                    class="sm:w-72"
                />

                <PrimaryLinkButton :href="route('tails.create')">
                    New Tail
                </PrimaryLinkButton>
            </div>
        </div>

        <div v-if="tails.data.length === 0" class="card mt-6">
            <div v-if="search" class="p-6 text-center text-sm text-gray-500">
                No tails match "{{ search }}".
            </div>

            <EmptyState
                v-else
                title="No tails yet"
                description="Tails are the charter-ready aircraft on file, with photos and amenities the Quotes module pulls into client PDFs automatically."
            >
                <PrimaryLinkButton :href="route('tails.create')">
                    New Tail
                </PrimaryLinkButton>
            </EmptyState>
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div class="card mt-6 hidden overflow-hidden sm:block">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Tail
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Operator
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Category
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                Max Pax
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="tail in tails.data"
                            :key="tail.id"
                            class="transition-colors duration-150 ease-in-out hover:bg-gray-50"
                        >
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium">
                                <button
                                    type="button"
                                    class="cursor-pointer text-accent-600 hover:text-accent-700 hover:underline"
                                    @click="openDetails(tail)"
                                >
                                    {{ tail.tail }}
                                </button>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ tail.operator }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                {{ tail.aircraft_speed_reference?.type_name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                {{ tail.category }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                {{ tail.max_pax }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <Link
                                    :href="route('tails.edit', tail.id)"
                                    class="text-accent-600 hover:text-accent-700"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    class="-my-1 ml-4 cursor-pointer rounded-lg px-2 py-1 text-red-600 transition duration-150 ease-in-out hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                    @click="confirmDeletion(tail)"
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
                    v-for="tail in tails.data"
                    :key="tail.id"
                    :delete-label="`Delete ${tail.tail}`"
                    @delete="confirmDeletion(tail)"
                >
                    <button
                        type="button"
                        class="cursor-pointer text-sm font-medium text-accent-600 hover:text-accent-700 hover:underline"
                        @click="openDetails(tail)"
                    >
                        {{ tail.tail }}
                    </button>

                    <dl class="mt-2 space-y-1 text-sm text-gray-600">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Operator</dt>
                            <dd class="text-right">{{ tail.operator }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Type</dt>
                            <dd class="text-right">{{ tail.aircraft_speed_reference?.type_name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Category</dt>
                            <dd class="text-right">{{ tail.category }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Max Pax</dt>
                            <dd class="text-right">{{ tail.max_pax }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex justify-end text-sm font-medium">
                        <Link
                            :href="route('tails.edit', tail.id)"
                            class="text-accent-600 hover:text-accent-700"
                        >
                            Edit
                        </Link>
                    </div>
                </SwipeableListItem>
            </div>
        </template>

        <Pagination :meta="tails" class="mt-4" />

        <!-- Details modal -->
        <Modal :show="selectedTail !== null" max-width="lg" @close="closeDetails">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">{{ selectedTail?.tail }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ selectedTail?.operator }}</p>

                <div
                    v-if="selectedTail?.photo1_url || selectedTail?.photo2_url"
                    class="mt-4 grid gap-3"
                    :class="selectedTail?.photo1_url && selectedTail?.photo2_url ? 'grid-cols-2' : 'grid-cols-1'"
                >
                    <img
                        v-if="selectedTail.photo1_url"
                        :src="selectedTail.photo1_url"
                        :alt="`${selectedTail.tail} photo 1`"
                        class="aspect-video w-full rounded-lg border border-gray-200 object-cover"
                    />
                    <img
                        v-if="selectedTail.photo2_url"
                        :src="selectedTail.photo2_url"
                        :alt="`${selectedTail.tail} photo 2`"
                        class="aspect-video w-full rounded-lg border border-gray-200 object-cover"
                    />
                </div>

                <dl class="mt-4 divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-gray-500">Category</dt>
                        <dd class="text-right text-gray-900">{{ selectedTail?.category }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-gray-500">Type</dt>
                        <dd class="text-right text-gray-900">{{ selectedTail?.aircraft_speed_reference?.type_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-gray-500">Year of make</dt>
                        <dd class="text-right text-gray-900">{{ selectedTail?.year_of_make }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-gray-500">Year of refurbishment</dt>
                        <dd class="text-right text-gray-900">{{ selectedTail?.year_of_refurbishment ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-gray-500">Max passengers</dt>
                        <dd class="text-right text-gray-900">{{ selectedTail?.max_pax }}</dd>
                    </div>
                </dl>

                <div v-if="cabinFields.length" class="mt-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Cabin (reference — from linked type)
                    </p>
                    <dl class="mt-2 divide-y divide-gray-100 text-sm">
                        <div v-for="[label, value] in cabinFields" :key="label" class="flex justify-between gap-4 py-2">
                            <dt class="text-gray-500">{{ label }}</dt>
                            <dd class="text-right text-gray-900">{{ value }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="activeAmenities.length" class="mt-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Amenities</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span v-for="label in activeAmenities" :key="label" class="badge badge-neutral">
                            {{ label }}
                        </span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDetails">Close</SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- Delete confirmation modal -->
        <Modal :show="tailPendingDeletion !== null" @close="closeDeletion">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Delete tail?</h2>

                <p class="mt-1 text-sm text-gray-600">
                    Are you sure you want to delete
                    <span class="font-medium text-gray-900">{{ tailPendingDeletion?.tail || 'this tail' }}</span>? This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDeletion">Cancel</SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :loading="form.processing"
                        @click="deleteTail"
                    >
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
