<script setup>
import Badge from '@/Components/Badge.vue';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    // The single history row (across the whole list, top-5 or full) with
    // a request in flight — owned by the parent (Quotes/Index.vue) since
    // both the inline top-5 and the "view all" popup render this same
    // component against the same underlying list and must agree on which
    // row is busy.
    busyId: {
        type: Number,
        default: null,
    },
});

defineEmits(['view', 'refresh', 'delete']);

const STATUS_LABELS = {
    pending: 'Pending',
    offers_received: 'Offers received',
};

const statusVariant = (status) => (status === 'offers_received' ? 'success' : 'neutral');

// A manually-created quote (see QuoteRequestController::store()) has no
// avinode_trip_id — reference_label stands in for it wherever the trip ID
// would otherwise show. Both are always one or the other in practice
// (reference_label is auto-generated when left blank — see
// QuoteRequestController::generateReferenceLabel()), the fallback string
// is just a last resort.
const historyLabel = (item) => item.avinode_trip_id ?? item.reference_label ?? 'Untitled quote';

// "04 Aug 2026 18:00 LFMN → LATI 19:35" — date/departure on the left,
// arrival on the right, each field just dropped if the server didn't have
// it (a trip with no offers yet sends schedule: null; an offer with no
// quoted arrival time still shows date + departure, just no "→" side).
const formatSchedule = (schedule) => {
    if (!schedule) {
        return '—';
    }

    const departure = [schedule.date, schedule.departure_time, schedule.departure_icao]
        .filter(Boolean)
        .join(' ');
    const arrival = [schedule.arrival_icao, schedule.arrival_time].filter(Boolean).join(' ');

    if (!departure && !arrival) {
        return '—';
    }

    return arrival ? `${departure} → ${arrival}` : departure;
};
</script>

<template>
    <div>
        <!-- Desktop table -->
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="py-2 pr-4">Trip ID</th>
                        <th class="py-2 pr-4">Schedule</th>
                        <th class="py-2 pr-4">Offers</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in items" :key="item.id">
                        <td class="py-2 pr-4">
                            <button
                                type="button"
                                class="font-medium text-accent-700 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline"
                                :disabled="busyId !== null"
                                @click="$emit('view', item)"
                            >
                                {{ historyLabel(item) }}
                            </button>
                        </td>
                        <td class="py-2 pr-4 whitespace-nowrap text-gray-600">{{ formatSchedule(item.schedule) }}</td>
                        <td class="py-2 pr-4 text-gray-600">{{ item.offers_count }}</td>
                        <td class="py-2 pr-4">
                            <Badge :variant="statusVariant(item.status)">
                                {{ STATUS_LABELS[item.status] ?? item.status }}
                            </Badge>
                        </td>
                        <td class="py-2 pr-4">
                            <div class="flex items-center justify-end gap-2">
                                <!-- No avinode_trip_id means no mailbox to
                                     re-search — see QuoteController::index()'s
                                     own doc comment on why Refresh stays
                                     trip_id-only. -->
                                <button
                                    v-if="item.avinode_trip_id"
                                    type="button"
                                    class="inline-flex items-center rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="busyId !== null"
                                    @click="$emit('refresh', item)"
                                >
                                    <svg
                                        v-if="busyId === item.id"
                                        class="-ml-0.5 mr-1.5 h-3.5 w-3.5 animate-spin"
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
                                    Refresh
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-lg border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 transition duration-150 ease-in-out hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="busyId !== null"
                                    @click="$emit('delete', item)"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile stacked cards — same data as the table above, one
             card per trip; matches the responsive table fallback used
             on Clients / Airports / Tails. -->
        <div class="space-y-2 md:hidden">
            <div
                v-for="item in items"
                :key="item.id"
                class="rounded-lg border border-gray-200 p-3"
            >
                <button
                    type="button"
                    class="text-sm font-medium text-accent-700 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline"
                    :disabled="busyId !== null"
                    @click="$emit('view', item)"
                >
                    {{ historyLabel(item) }}
                </button>

                <p class="mt-0.5 text-xs text-gray-500">
                    {{ formatSchedule(item.schedule) }}
                </p>

                <div class="mt-1.5 flex items-center gap-3 text-sm text-gray-600">
                    <span>
                        {{ item.offers_count }}
                        {{ item.offers_count === 1 ? 'offer' : 'offers' }}
                    </span>
                    <Badge :variant="statusVariant(item.status)">
                        {{ STATUS_LABELS[item.status] ?? item.status }}
                    </Badge>

                    <div class="ml-auto flex shrink-0 items-center gap-2">
                        <button
                            v-if="item.avinode_trip_id"
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 p-1.5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="busyId !== null"
                            :aria-label="`Refresh ${historyLabel(item)}`"
                            @click="$emit('refresh', item)"
                        >
                            <svg
                                class="h-4 w-4"
                                :class="{ 'animate-spin': busyId === item.id }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-red-200 p-1.5 text-red-600 transition duration-150 ease-in-out hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="busyId !== null"
                            :aria-label="`Delete ${historyLabel(item)} from history`"
                            @click="$emit('delete', item)"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
