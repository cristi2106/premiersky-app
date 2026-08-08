<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, router, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    aircraft: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    syncStatus: {
        type: Object,
        required: true,
    },
});

const search = ref(props.filters.search);
const selectedAircraft = ref(null);
const syncing = ref(props.syncStatus.running);

let searchDebounce = null;

watch(search, (value) => {
    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        router.get(
            route('charter-fleet.index'),
            value ? { search: value } : {},
            { preserveState: true, preserveScroll: true, replace: true, only: ['aircraft', 'filters'] }
        );
    }, 300);
});

// A sync can take several minutes (it pages through thousands of records),
// so it runs as a queued job — poll for its status instead of blocking.
const { start: startPolling, stop: stopPolling } = usePoll(
    3000,
    { only: ['aircraft', 'syncStatus'] },
    { autoStart: false }
);

watch(
    () => props.syncStatus.running,
    (running) => {
        syncing.value = running;

        if (running) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true }
);

const runSync = () => {
    syncing.value = true;

    router.post(
        route('charter-fleet.sync'),
        {},
        { preserveScroll: true, preserveState: true, onSuccess: () => startPolling() }
    );
};

const lastSyncSummary = computed(() => {
    const summary = props.syncStatus.summary;

    if (!summary || syncing.value) {
        return null;
    }

    const finishedAt = new Date(summary.finished_at).toLocaleString();

    if (summary.failed) {
        return `Sync failed at ${finishedAt}: ${summary.error}`;
    }

    const total = summary.imported + summary.updated;

    return `Last synced: ${total} aircraft (${summary.imported} new, ${summary.updated} updated, ${summary.skipped} skipped) at ${finishedAt}`;
});

const openDetails = (item) => {
    selectedAircraft.value = item;
};

const closeModal = () => {
    selectedAircraft.value = null;
};

const yesNo = (value) => (value === null || value === undefined ? null : value ? 'Yes' : 'No');

const detailFields = computed(() => {
    const a = selectedAircraft.value;

    if (!a) {
        return [];
    }

    return [
        ['Aircraft type', a.aircraft_type_name],
        ['ICAO type code', a.aircraft_type_icao],
        ['Class', a.aircraft_class],
        ['Operator', a.operator_name],
        ['Year of production', a.year_of_production],
        ['Max passengers', a.passengers_max],
        ['Lavatory', yesNo(a.lavatory)],
        ['Beds', a.beds],
        ['Wireless internet', yesNo(a.wireless_internet)],
        ['Entertainment system', yesNo(a.entertainment_system)],
        ['Pets allowed', yesNo(a.pets_allowed)],
        ['Smoking', yesNo(a.smoking)],
        ['Cabin height (m)', a.cabin_height],
        ['Cabin length (m)', a.cabin_length],
        ['Cabin width (m)', a.cabin_width],
        ['Luggage volume (m³)', a.luggage_volume],
        ['Sleeping places', a.sleeping_places],
        ['Divan seats', a.divan_seats],
        ['Hot meal', yesNo(a.hot_meal)],
        ['Medical ramp', yesNo(a.medical_ramp)],
        ['Refurbished', a.refurbishment],
    ].filter(([, value]) => value !== null && value !== undefined && value !== '');
});
</script>

<template>
    <Head title="Charter Fleet Directory" />

    <AdminLayout title="Charter Fleet Directory">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-600">{{ aircraft.total }} aircraft</p>
                <p v-if="syncing" class="mt-0.5 text-xs text-gray-400">
                    Sync in progress… this can take a few minutes for a full sync.
                </p>
                <p v-else-if="lastSyncSummary" class="mt-0.5 text-xs text-gray-400">
                    {{ lastSyncSummary }}
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput
                    v-model="search"
                    placeholder="Search by registration or operator…"
                    class="sm:w-72"
                />

                <PrimaryButton
                    type="button"
                    :class="{ 'opacity-25': syncing }"
                    :disabled="syncing"
                    @click="runSync"
                >
                    <svg
                        v-if="syncing"
                        class="-ml-1 mr-2 h-4 w-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                        />
                    </svg>
                    {{ syncing ? 'Sync in progress…' : 'Sync Now' }}
                </PrimaryButton>
            </div>
        </div>

        <div v-if="aircraft.data.length === 0" class="card mt-6">
            <div class="p-6 text-center text-sm text-gray-500">
                <template v-if="search">
                    No aircraft match "{{ search }}".
                </template>
                <template v-else>
                    No aircraft yet. Click "Sync Now" above, or run
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">php artisan fleet:sync</code>.
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
                                Registration
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Operator
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Year
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="item in aircraft.data"
                            :key="item.id"
                            class="transition duration-100 ease-in-out hover:bg-gray-50"
                        >
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium">
                                <button
                                    type="button"
                                    class="cursor-pointer text-accent-600 hover:text-accent-700 hover:underline"
                                    @click="openDetails(item)"
                                >
                                    {{ item.registration_number }}
                                </button>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ item.operator_name || '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                {{ item.year_of_production ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile stacked cards -->
            <div class="mt-6 space-y-4 sm:hidden">
                <div v-for="item in aircraft.data" :key="item.id" class="card p-4">
                    <button
                        type="button"
                        class="cursor-pointer text-sm font-medium text-accent-600 hover:text-accent-700 hover:underline"
                        @click="openDetails(item)"
                    >
                        {{ item.registration_number }}
                    </button>

                    <dl class="mt-2 space-y-1 text-sm text-gray-600">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Operator</dt>
                            <dd class="text-right">{{ item.operator_name || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Year</dt>
                            <dd class="text-right">{{ item.year_of_production ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </template>

        <Pagination :meta="aircraft" class="mt-4" />

        <Modal :show="selectedAircraft !== null" max-width="lg" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ selectedAircraft?.registration_number }}
                </h2>
                <p v-if="selectedAircraft?.aircraft_type_name" class="mt-1 text-sm text-gray-500">
                    {{ selectedAircraft.aircraft_type_name }}
                </p>

                <img
                    v-if="selectedAircraft?.exterior_image_url"
                    :src="selectedAircraft.exterior_image_url"
                    :alt="`${selectedAircraft.registration_number} exterior`"
                    class="mt-4 aspect-video w-full rounded-lg border border-gray-200 object-cover"
                />

                <dl class="mt-4 divide-y divide-gray-100 text-sm">
                    <div
                        v-for="[label, value] in detailFields"
                        :key="label"
                        class="flex justify-between gap-4 py-2"
                    >
                        <dt class="text-gray-500">{{ label }}</dt>
                        <dd class="text-right text-gray-900">{{ value }}</dd>
                    </div>
                </dl>

                <p v-if="selectedAircraft?.description" class="mt-4 text-sm text-gray-600">
                    {{ selectedAircraft.description }}
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">Close</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
