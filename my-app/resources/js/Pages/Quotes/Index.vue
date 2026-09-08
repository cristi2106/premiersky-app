<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AddOfferModal from '@/Components/AddOfferModal.vue';
import CreateManualQuoteModal from '@/Components/CreateManualQuoteModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import QuoteHistoryList from '@/Components/QuoteHistoryList.vue';
import QuoteOfferCard from '@/Components/QuoteOfferCard.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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
    // Set only when QuoteOfferController::generateContract() bailed out
    // before creating anything (no client selected, or no confident
    // schedule to build a leg from) and redirected back here instead of
    // to the new contract's edit page — see that method's own doc
    // comment.
    contractError: {
        type: String,
        default: null,
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

// Top 5 inline, same order the server already sends (most recent first);
// the rest are only ever seen through the "view all" popup below.
const topHistory = computed(() => props.history.slice(0, 5));
const historyModalOpen = ref(false);

// By id, not trip_id — works identically for an email-pulled quote and a
// manually-created one (which has no avinode_trip_id to route by at all —
// see QuoteRequestController::store()). QuoteController::index() resolves
// this straight to the exact row this list already picked as canonical
// for its group, so it's never a different lookup than trip_id+view=1
// used to be — see that method's own doc comment.
const viewHistoryItem = (item) => {
    if (historyBusyId.value !== null) {
        return;
    }

    historyBusyId.value = item.id;
    historyModalOpen.value = false;

    router.get(
        route('quotes.index'),
        { quote_request_id: item.id },
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

// --- Deleting history ---
//
// Both actions hard-delete the QuoteRequest(s) and (via ON DELETE CASCADE
// on quote_offers.quote_request_id, reinforced server-side) their offers.
// A Contract already generated from one of those offers is a standalone
// record with no link back to the quote, so it is never touched — see
// QuoteRequestController::destroy(). Shared useForm: only one confirm
// modal is ever open at a time, so one `processing` flag is enough.
const deleteForm = useForm({});
// The history row awaiting a per-trip delete confirmation, or null.
const historyItemPendingDeletion = ref(null);
// Whether the "clear everything" confirmation is open.
const clearHistoryConfirmOpen = ref(false);

const confirmHistoryItemDeletion = (item) => {
    if (historyBusyId.value !== null) {
        return;
    }
    historyItemPendingDeletion.value = item;
};

const confirmClearHistory = () => {
    clearHistoryConfirmOpen.value = true;
};

const closeDeleteModals = () => {
    historyItemPendingDeletion.value = null;
    clearHistoryConfirmOpen.value = false;
};

const deleteHistoryItem = () => {
    deleteForm.delete(route('quote-requests.destroy', historyItemPendingDeletion.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModals,
    });
};

const clearHistory = () => {
    deleteForm.delete(route('quote-requests.clear-history'), {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModals();
            // Nothing left for it to show — unlike a single-row delete
            // (deleteHistoryItem above), which leaves the "view all"
            // popup open on purpose so the rest of the list stays visible.
            historyModalOpen.value = false;
        },
    });
};

// Same fallback QuoteHistoryList uses for its own rows — needed again
// here for the delete-confirmation copy below, which isn't rendered by
// that component.
const historyLabel = (item) => item?.avinode_trip_id ?? item?.reference_label ?? 'Untitled quote';

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

// The trip's own schedule, shown once above the offer list — the server
// already picked the best available itinerary across every offer (see
// QuoteController::resolveTripSchedule()), so this is just the prop.
const tripSchedule = computed(() => props.quoteRequest?.schedule ?? null);

// A quote with no avinode_trip_id was created by hand (see
// QuoteRequestController::store()) — only that kind has an editable
// schedule (quote_request_legs); an email-pulled quote's keeps coming
// from parsed offer data, as it already did.
const isManualQuote = computed(() => props.quoteRequest !== null && !props.quoteRequest.avinode_trip_id);

// "Apply to all" for the commission fields below — quote-wide, so it
// lives here rather than inside any one QuoteOfferCard. Anchored to
// props.offers[0] (the cheapest offer, i.e. this quote's "Option 1" in
// both the server's own ordering and the generated PDF) rather than
// sortedOffers[0], so the checkbox stays put on the same card instead of
// jumping to a different one if the price-sort toggle above is flipped.
const applyCommissionToAll = ref(false);
// The last {type, value} committed by whichever offer was edited while
// applyCommissionToAll was checked; null until that first happens, so
// simply checking the box doesn't itself change anything.
const sharedCommission = ref(null);
const onCommissionChanged = (payload) => {
    sharedCommission.value = payload;
};

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

// --- Manually-added offers ---
//
// For an operator that responded by phone or another channel instead of
// email — see AddOfferModal and QuoteOfferController::store(). Submitting
// it is a full Inertia redirect back to this same page (not an axios
// call), so the new offer arrives already merged into props.offers
// alongside everything else that changes with it (status, history) —
// nothing to reconcile locally here.
const addOfferModalOpen = ref(false);

// --- Manually-created quotes ---
//
// For a trip that never came through Avinode/email at all — see
// CreateManualQuoteModal and QuoteRequestController::store(). Submitting
// it redirects straight to the new quote's own offer page (by
// quote_request_id, since it has no avinode_trip_id — same reasoning as
// viewHistoryItem() above), so there's nothing to reconcile locally here
// either.
const createQuoteModalOpen = ref(false);

// True whenever there's an actual quote loaded to show the rest of the
// page for — either a trip_id pull/view (tripId non-empty) or a
// quote_request_id load (which can leave tripId empty, for a manually-
// created quote — see QuoteController::index()). Replaces a bare
// `tripId` check, which used to gate this whole section and would have
// hidden it entirely for a manual quote.
const hasActiveQuote = computed(() => props.tripId !== '' || props.quoteRequest !== null);
</script>

<template>
    <Head title="Quotes" />

    <AdminLayout title="Quotes">
        <div
            v-if="contractError"
            class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
        >
            {{ contractError }}
        </div>

        <!-- Search history — every trip ID already searched, plus every
             manually-created quote, so none of them have to be remembered
             or retyped. Clicking a row loads whatever's already stored
             (no mailbox hit); Refresh is the explicit way to pull an
             email-pulled trip fresh instead (not offered for a manual
             quote — see QuoteHistoryList). Only the 5 most recent show
             inline; "View all" opens the rest (up to the 100
             QuoteController::searchHistory() sends down) in a popup using
             the exact same list component, so the two never drift apart. -->
        <div v-if="history.length > 0" class="card p-4 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-medium text-gray-900">Search history</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Trips you've already pulled or created. Click one to view its
                        offers, or refresh it to pull the mailbox again.
                    </p>
                </div>

                <button
                    type="button"
                    class="shrink-0 inline-flex items-center rounded-lg border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 transition duration-150 ease-in-out hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="historyBusyId !== null"
                    @click="confirmClearHistory"
                >
                    Clear History
                </button>
            </div>

            <QuoteHistoryList
                class="mt-4"
                :items="topHistory"
                :busy-id="historyBusyId"
                @view="viewHistoryItem"
                @refresh="refreshHistoryItem"
                @delete="confirmHistoryItemDeletion"
            />

            <button
                v-if="history.length > 5"
                type="button"
                class="mt-4 text-sm font-medium text-accent-700 hover:underline"
                @click="historyModalOpen = true"
            >
                View all {{ history.length }}
            </button>
        </div>

        <div class="mt-6 flex flex-col gap-4 sm:flex-row">
            <div class="card flex-1 p-4 sm:p-6">
                <h2 class="text-sm font-medium text-gray-900">Pull emails for a trip</h2>

                <form
                    class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end"
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

                    <PrimaryButton
                        type="submit"
                        :class="{ 'opacity-25': pulling }"
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
                    </PrimaryButton>
                </form>
            </div>

            <!-- For a trip that never came through Avinode/email at all —
                 see CreateManualQuoteModal and QuoteRequestController::store(). -->
            <div class="card flex flex-col justify-center p-4 sm:w-64 sm:p-6">
                <h2 class="text-sm font-medium text-gray-900">Not from Avinode?</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Build a quote by hand instead.
                </p>
                <SecondaryButton
                    type="button"
                    class="mt-3 justify-center"
                    @click="createQuoteModalOpen = true"
                >
                    Create Manual Quote
                </SecondaryButton>
            </div>
        </div>

        <div
            v-if="searchError"
            class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
        >
            {{ searchError }}
        </div>

        <template v-else-if="hasActiveQuote">
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
            <p v-else-if="tripId" class="mt-6 text-sm text-gray-600">
                Showing previously imported offers for
                <span class="font-medium text-gray-900">{{ tripId }}</span>
                from search history — the mailbox wasn't checked again. Use
                Refresh above to pull the latest.
            </p>
            <p v-else class="mt-6 text-sm text-gray-600">
                Manually created quote<span v-if="quoteRequest?.reference_label">
                    — <span class="font-medium text-gray-900">{{ quoteRequest.reference_label }}</span></span>.
                No mailbox involved — use "Add Offer" below to add options as operators respond.
            </p>

            <div v-if="pulled && emails.length === 0" class="card mt-4">
                <EmptyState
                    title="No matching emails"
                    :description="`Nothing in the mailbox matched “${tripId}” in the subject or body. Double-check the trip ID, or try again once the operator has replied.`"
                />
            </div>

            <template v-else>
                <!-- Client-facing quotation: who it's for, and a PDF built
                     from whichever offers are checked below. Kept separate
                     from "Pull Emails" — generating a PDF never re-hits
                     the mailbox. -->
                <div class="card mt-6 p-4 sm:p-6">
                    <h2 class="text-sm font-medium text-gray-900">Client-facing quotation</h2>

                    <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
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
                            <PrimaryButton
                                type="button"
                                :class="{ 'opacity-25': pdfHint !== null }"
                                :disabled="pdfHint !== null"
                                @click="generatePdf"
                            >
                                Generate PDF
                            </PrimaryButton>
                            <p v-if="pdfHint" class="mt-1 text-xs text-gray-500">{{ pdfHint }}</p>
                        </div>
                    </div>
                </div>

                <!-- Trip schedule — shown once, above the offers below,
                     since every one of them is the same trip on a
                     different aircraft: date/departure/arrival don't vary
                     by which option gets picked. Local time only, same as
                     each offer card used to show per-offer — see
                     AvinodeQuoteEmailParser, which never extracts the raw
                     email's UTC figures in the first place. -->
                <div v-if="tripSchedule || isManualQuote" class="card mt-6 p-4 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-medium text-gray-900">Schedule</h2>

                        <!-- Only a manually-created quote's schedule lives
                             on quote_request_legs, editable here — an
                             email-pulled quote's keeps coming from the
                             parsed offer data instead, as it already did.
                             See QuoteRequestController::editSchedule(). -->
                        <Link
                            v-if="isManualQuote"
                            :href="route('quote-requests.schedule.edit', quoteRequest.id)"
                            class="shrink-0 inline-flex items-center rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-1"
                        >
                            {{ tripSchedule ? 'Edit Schedule' : 'Set Up Schedule' }}
                        </Link>
                    </div>

                    <template v-if="tripSchedule">
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm">
                            <p class="text-gray-900">
                                <span class="font-medium">{{ tripSchedule.departure_time || '—' }}</span>
                                {{ tripSchedule.departure_airport || '—' }}
                            </p>
                            <p class="text-gray-400">→</p>
                            <p class="text-gray-900">
                                <span class="font-medium">{{ tripSchedule.arrival_time || '—' }}</span>
                                {{ tripSchedule.arrival_airport || '—' }}
                            </p>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ tripSchedule.departure_date || '—' }}
                            <span v-if="tripSchedule.pax"> · {{ tripSchedule.pax }} PAX</span>
                            <span v-if="!tripSchedule.arrival_time"> · arrival time not quoted yet</span>
                        </p>
                    </template>
                    <p v-else class="mt-3 text-sm text-gray-500">
                        No schedule set up yet.
                    </p>
                </div>

                <!-- Offers — the parsed, structured, bookable result. Only
                     ACCEPTED aircraft lines ever become one of these; a
                     matched email that was all declines is expected to
                     contribute nothing here. -->
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-medium text-gray-900">
                        Offers ({{ offers.length }})
                    </h2>

                    <div class="flex items-center gap-3">
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

                        <SecondaryButton type="button" @click="addOfferModalOpen = true">
                            Add Offer
                        </SecondaryButton>
                    </div>
                </div>

                <div v-if="offers.length === 0" class="card mt-2">
                    <EmptyState
                        title="No offers yet"
                        description="None of the matched emails had an accepted aircraft line — a declines-only thread still matches the search but has nothing to compare here."
                    />
                </div>

                <div v-else class="mt-2 space-y-2">
                    <QuoteOfferCard
                        v-for="offer in sortedOffers"
                        :key="offer.id"
                        :offer="offer"
                        :apply-to-all="applyCommissionToAll"
                        :shared-commission="sharedCommission"
                        :show-apply-to-all-checkbox="offer.id === offers[0]?.id"
                        :has-client="clientId !== null"
                        @update:apply-to-all="applyCommissionToAll = $event"
                        @commission-changed="onCommissionChanged"
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

        <!-- Add Offer -->
        <AddOfferModal
            v-if="quoteRequest"
            :show="addOfferModalOpen"
            :quote-request-id="quoteRequest.id"
            @close="addOfferModalOpen = false"
        />

        <!-- Create Manual Quote -->
        <CreateManualQuoteModal
            :show="createQuoteModalOpen"
            @close="createQuoteModalOpen = false"
        />

        <!-- Full search history — the same rows the top-5 preview shows,
             just every one of them (up to the 100
             QuoteController::searchHistory() sends down). Same
             QuoteHistoryList, same handlers — a row clicked here goes
             through the exact same viewHistoryItem() as the inline list. -->
        <Modal :show="historyModalOpen" max-width="2xl" @close="historyModalOpen = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Search history ({{ history.length }})
                </h2>

                <QuoteHistoryList
                    class="mt-4 max-h-[60vh] overflow-y-auto"
                    :items="history"
                    :busy-id="historyBusyId"
                    @view="viewHistoryItem"
                    @refresh="refreshHistoryItem"
                    @delete="confirmHistoryItemDeletion"
                />

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="historyModalOpen = false">
                        Close
                    </SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- Per-trip delete confirmation -->
        <Modal :show="historyItemPendingDeletion !== null" @close="closeDeleteModals">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Delete this quote from history?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    <span class="font-medium text-gray-900">{{ historyLabel(historyItemPendingDeletion) }}</span>
                    and its
                    {{ historyItemPendingDeletion?.offers_count }}
                    imported offer{{ historyItemPendingDeletion?.offers_count === 1 ? '' : 's' }}
                    will be permanently deleted. This won't be recoverable. Any
                    contract already generated from this quote is kept.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDeleteModals">
                        Cancel
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :loading="deleteForm.processing"
                        @click="deleteHistoryItem"
                    >
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Clear-all confirmation -->
        <Modal :show="clearHistoryConfirmOpen" @close="closeDeleteModals">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Clear all search history?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    All {{ history.length }} trip{{ history.length === 1 ? '' : 's' }}
                    and every imported offer will be permanently deleted. This
                    won't be recoverable. Contracts already generated from these
                    quotes are kept.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDeleteModals">
                        Cancel
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :loading="deleteForm.processing"
                        @click="clearHistory"
                    >
                        Clear History
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
