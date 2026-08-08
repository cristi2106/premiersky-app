<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import QuoteOfferCard from '@/Components/QuoteOfferCard.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tripId: {
        type: String,
        default: '',
    },
    emails: {
        type: Array,
        required: true,
    },
    totalMatches: {
        type: Number,
        default: 0,
    },
    truncated: {
        type: Boolean,
        default: false,
    },
    searchScope: {
        type: String,
        default: null,
    },
    searchError: {
        type: String,
        default: null,
    },
    pulled: {
        type: Boolean,
        default: true,
    },
    offers: {
        type: Array,
        required: true,
    },
    quoteRequest: {
        type: Object,
        default: null,
    },
    history: {
        type: Array,
        default: () => [],
    },
});

// Local, editable copy of the trip ID field — props.tripId only reflects
// the last *submitted* search, so it shouldn't drive the input directly.
const tripIdInput = ref(props.tripId);
const pulling = ref(false);

const RELOAD_KEYS = [
    'tripId', 'emails', 'totalMatches', 'truncated', 'searchScope',
    'searchError', 'pulled', 'offers', 'quoteRequest', 'history',
];

const pullEmails = () => {
    const value = tripIdInput.value.trim();

    if (value === '' || pulling.value) {
        return;
    }

    pulling.value = true;

    router.get(
        route('quotes.index'),
        { trip_id: value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: RELOAD_KEYS,
            onFinish: () => {
                pulling.value = false;
            },
        }
    );
};

// --- Search history ---
//
// Loading a past trip ID from history is a full navigation (not a
// preserveState partial reload like pullEmails above) — every field on
// the page genuinely changes when switching trips, and remounting lets
// tripIdInput, priceSort, the client picker etc. all just re-initialize
// from the freshly loaded props instead of needing to be reset by hand.

// Tracks which single history row has a request in flight, so only that
// row's button shows a spinner rather than the whole page looking busy.
const historyBusyId = ref(null);

const viewHistoryItem = (item) => {
    if (historyBusyId.value !== null) {
        return;
    }

    historyBusyId.value = item.id;

    router.get(
        route('quotes.index'),
        { trip_id: item.avinode_trip_id, view: 1 },
        { onFinish: () => { historyBusyId.value = null; } }
    );
};

const refreshHistoryItem = (item) => {
    if (historyBusyId.value !== null) {
        return;
    }

    historyBusyId.value = item.id;

    router.get(
        route('quotes.index'),
        { trip_id: item.avinode_trip_id },
        { onFinish: () => { historyBusyId.value = null; } }
    );
};

const STATUS_LABELS = {
    pending: 'Pending',
    offers_received: 'Offers received',
};

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

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

// Client-side only — the server already sends offers ordered by price
// ascending, so re-sorting here (rather than round-tripping to the
// server) is instant and keeps the "selected" checkboxes' saved state
// untouched either way.
const priceSort = ref('asc');

const sortedOffers = computed(() =>
    [...props.offers].sort((a, b) =>
        priceSort.value === 'asc'
            ? a.offered_price - b.offered_price
            : b.offered_price - a.offered_price
    )
);

// --- Client-facing quotation PDF ---

const clientId = ref(props.quoteRequest?.client?.id ?? null);
const clientLabel = (client) => client.company_name || 'Untitled client';

const onClientSelect = async (option) => {
    if (!props.quoteRequest) {
        return;
    }

    await window.axios.patch(route('quote-requests.update', props.quoteRequest.id), {
        client_id: option?.id ?? null,
    });
};

// offers[].selected is mutated directly by QuoteOfferCard (same pattern it
// already uses for commission fields) — reading it here just needs the
// prop's reactivity, no extra event plumbing.
const hasSelectedOffers = computed(() => props.offers.some((offer) => offer.selected));

const pdfHint = computed(() => {
    if (!clientId.value) {
        return 'Select a client to generate a quotation PDF.';
    }

    if (!hasSelectedOffers.value) {
        return 'Select at least one offer to generate a quotation PDF.';
    }

    return null;
});

const generatePdf = () => {
    if (pdfHint.value || !props.quoteRequest) {
        return;
    }

    window.open(route('quote-requests.pdf', props.quoteRequest.id), '_blank');
};
</script>

<template>
    <Head title="Quotes" />

    <AdminLayout title="Quotes">
        <!-- Search history — every trip ID already searched, so none of
             them have to be remembered or retyped. Clicking a trip ID
             loads whatever's already stored (no mailbox hit); Refresh is
             the explicit way to pull that trip fresh instead. -->
        <div v-if="history.length > 0" class="card p-4 sm:p-6">
            <h2 class="text-sm font-medium text-gray-900">Search history</h2>
            <p class="mt-1 text-sm text-gray-600">
                Trip IDs you've already pulled. Click one to view its offers,
                or refresh it to pull the mailbox again.
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-4">Trip ID</th>
                            <th class="py-2 pr-4">Schedule</th>
                            <th class="py-2 pr-4">First searched</th>
                            <th class="py-2 pr-4">Offers</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in history" :key="item.id">
                            <td class="py-2 pr-4">
                                <button
                                    type="button"
                                    class="font-medium text-accent-700 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline"
                                    :disabled="historyBusyId !== null"
                                    @click="viewHistoryItem(item)"
                                >
                                    {{ item.avinode_trip_id }}
                                </button>
                            </td>
                            <td class="py-2 pr-4 whitespace-nowrap text-gray-600">{{ formatSchedule(item.schedule) }}</td>
                            <td class="py-2 pr-4 text-gray-600">{{ formatDate(item.first_searched_at) }}</td>
                            <td class="py-2 pr-4 text-gray-600">{{ item.offers_count }}</td>
                            <td class="py-2 pr-4">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="item.status === 'offers_received'
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-700'"
                                >
                                    {{ STATUS_LABELS[item.status] ?? item.status }}
                                </span>
                            </td>
                            <td class="py-2 pr-4 text-right">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="historyBusyId !== null"
                                    @click="refreshHistoryItem(item)"
                                >
                                    <svg
                                        v-if="historyBusyId === item.id"
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
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-6 p-4 sm:p-6">
            <h2 class="text-sm font-medium text-gray-900">Pull emails for a trip</h2>
            <p class="mt-1 text-sm text-gray-600">
                Paste an Avinode trip ID below. This searches email subjects
                first, which is fast, and only falls back to also scanning
                message bodies if the subject search comes back empty — the
                mailbox is large and isn't indexed for full-text search, so
                that fallback can take up to a minute.
            </p>

            <form
                class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end"
                @submit.prevent="pullEmails"
            >
                <div class="flex-1">
                    <InputLabel for="trip_id" value="Avinode trip ID" />
                    <TextInput
                        id="trip_id"
                        v-model="tripIdInput"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="e.g. TRP-123456"
                        autofocus
                    />
                </div>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-transparent bg-gray-900 px-4 py-2 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 active:bg-gray-950 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="pulling || tripIdInput.trim() === ''"
                >
                    <svg
                        v-if="pulling"
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
                    {{ pulling ? 'Pulling…' : 'Pull Emails' }}
                </button>
            </form>
        </div>

        <div
            v-if="searchError"
            class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
        >
            {{ searchError }}
        </div>

        <template v-else-if="tripId">
            <template v-if="pulled">
                <p class="mt-6 text-sm text-gray-600">
                    {{ totalMatches }} email{{ totalMatches === 1 ? '' : 's' }} found for
                    <span class="font-medium text-gray-900">{{ tripId }}</span>
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    <template v-if="searchScope === 'subject'">Searched subjects only.</template>
                    <template v-else-if="searchScope === 'subject_and_body'">
                        Subject-only search found nothing, so this also scanned message bodies.
                    </template>
                </p>
                <p v-if="truncated" class="mt-1 text-sm text-amber-700">
                    Showing the {{ emails.length }} most recent — narrow the trip ID to see the rest.
                </p>
            </template>
            <p v-else class="mt-6 text-sm text-gray-600">
                Showing previously imported offers for
                <span class="font-medium text-gray-900">{{ tripId }}</span>
                from search history — the mailbox wasn't checked again. Use
                Refresh above to pull the latest.
            </p>

            <div v-if="pulled && emails.length === 0" class="card mt-4">
                <div class="p-6 text-center text-sm text-gray-500">
                    No emails matched "{{ tripId }}" in the subject or body.
                </div>
            </div>

            <template v-else>
                <!-- Client-facing quotation: who it's for, and a PDF built
                     from whichever offers are checked below. Kept separate
                     from "Pull Emails" — generating a PDF never re-hits
                     the mailbox. -->
                <div class="card mt-6 p-4 sm:p-6">
                    <h2 class="text-sm font-medium text-gray-900">Client-facing quotation</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Pick the client this quote is for, then generate a PDF
                        from whichever offers have their checkbox selected
                        below. Operator names, tail numbers and Avinode
                        references never appear on it.
                    </p>

                    <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="max-w-sm flex-1">
                            <InputLabel for="quote_client" value="Client" />
                            <SearchableSelect
                                id="quote_client"
                                v-model="clientId"
                                :search-url="route('clients.search')"
                                :option-label="clientLabel"
                                :initial-label="quoteRequest?.client ? clientLabel(quoteRequest.client) : null"
                                placeholder="Search clients…"
                                class="mt-1"
                                @select="onClientSelect"
                            >
                                <template #option="{ option }">
                                    <span class="text-gray-900">{{ clientLabel(option) }}</span>
                                </template>
                            </SearchableSelect>
                        </div>

                        <div class="text-right">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-gray-900 px-4 py-2 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 active:bg-gray-950 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="pdfHint !== null"
                                @click="generatePdf"
                            >
                                Generate PDF
                            </button>
                            <p v-if="pdfHint" class="mt-1 text-xs text-gray-500">{{ pdfHint }}</p>
                        </div>
                    </div>
                </div>

                <!-- Offers — the parsed, structured, bookable result. Only
                     ACCEPTED aircraft lines ever become one of these; a
                     matched email that was all declines is expected to
                     contribute nothing here. -->
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-medium text-gray-900">
                        Offers ({{ offers.length }})
                    </h2>

                    <div v-if="offers.length > 1" class="flex items-center gap-2">
                        <label for="price_sort" class="text-xs text-gray-500">Sort by price</label>
                        <select
                            id="price_sort"
                            v-model="priceSort"
                            class="rounded-lg border-gray-300 py-1.5 pl-3 pr-8 text-sm text-gray-900 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                        >
                            <option value="asc">Low to high</option>
                            <option value="desc">High to low</option>
                        </select>
                    </div>
                </div>

                <div v-if="offers.length === 0" class="card mt-2">
                    <div class="p-6 text-center text-sm text-gray-500">
                        None of the matched emails had an accepted offer yet.
                    </div>
                </div>

                <div v-else class="mt-2 space-y-4">
                    <QuoteOfferCard
                        v-for="offer in sortedOffers"
                        :key="offer.id"
                        :offer="offer"
                    />
                </div>

                <!-- Raw matched emails — kept for reference/audit (also
                     covers matches that never produced an offer at all,
                     e.g. an all-declines thread), collapsed by default so
                     the offers above stay the focus. Only meaningful right
                     after an actual mailbox pull — a view-only history load
                     never fetched any email bodies to show here. -->
                <details v-if="pulled" class="mt-6 group">
                    <summary class="cursor-pointer text-sm font-medium text-gray-900 select-none">
                        Raw matched emails ({{ emails.length }})
                    </summary>

                    <div class="mt-2 space-y-4">
                        <div v-for="(email, index) in emails" :key="index" class="card p-4 sm:p-6">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ email.subject || '(no subject)' }}
                                </p>
                                <p class="shrink-0 text-xs text-gray-500">
                                    {{ formatDate(email.date) }}
                                </p>
                            </div>
                            <p class="mt-1 text-sm text-gray-600">
                                From: {{ email.from || '—' }}
                            </p>

                            <pre class="mt-4 max-h-96 overflow-y-auto whitespace-pre-wrap break-words rounded-lg bg-gray-50 p-4 font-sans text-sm text-gray-700">{{ email.body || '(empty body)' }}</pre>
                        </div>
                    </div>
                </details>
            </template>
        </template>
    </AdminLayout>
</template>
