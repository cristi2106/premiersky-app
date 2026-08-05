<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import QuoteOfferCard from '@/Components/QuoteOfferCard.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    offers: {
        type: Array,
        required: true,
    },
});

// Local, editable copy of the trip ID field — props.tripId only reflects
// the last *submitted* search, so it shouldn't drive the input directly.
const tripIdInput = ref(props.tripId);
const pulling = ref(false);

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
            only: ['tripId', 'emails', 'totalMatches', 'truncated', 'searchScope', 'searchError', 'offers'],
            onFinish: () => {
                pulling.value = false;
            },
        }
    );
};

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};
</script>

<template>
    <Head title="Quotes" />

    <AdminLayout title="Quotes">
        <div class="card p-4 sm:p-6">
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

            <div v-if="emails.length === 0" class="card mt-4">
                <div class="p-6 text-center text-sm text-gray-500">
                    No emails matched "{{ tripId }}" in the subject or body.
                </div>
            </div>

            <template v-else>
                <!-- Offers — the parsed, structured, bookable result. Only
                     ACCEPTED aircraft lines ever become one of these; a
                     matched email that was all declines is expected to
                     contribute nothing here. -->
                <h2 class="mt-6 text-sm font-medium text-gray-900">
                    Offers ({{ offers.length }})
                </h2>

                <div v-if="offers.length === 0" class="card mt-2">
                    <div class="p-6 text-center text-sm text-gray-500">
                        None of the matched emails had an accepted offer yet.
                    </div>
                </div>

                <div v-else class="mt-2 space-y-4">
                    <QuoteOfferCard
                        v-for="offer in offers"
                        :key="offer.id"
                        :offer="offer"
                    />
                </div>

                <!-- Raw matched emails — kept for reference/audit (also
                     covers matches that never produced an offer at all,
                     e.g. an all-declines thread), collapsed by default so
                     the offers above stay the focus. -->
                <details class="mt-6 group">
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
