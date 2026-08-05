<script setup>
import Badge from '@/Components/Badge.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    offer: {
        type: Object,
        required: true,
    },
});

// Local editable copies — the offer prop only reflects the last value the
// server confirmed, so typing shouldn't wait on a round-trip to show up.
const commissionType = ref(props.offer.commission_type);
const commissionValue = ref(
    props.offer.commission_value !== null ? String(props.offer.commission_value) : ''
);
const saving = ref(false);
const savedAt = ref(null);

// Instant feedback as you type — mirrors QuoteOffer::calculateFinalPrice()
// exactly, but the persisted number always comes back from the server
// afterwards, since that's the one that's trusted.
const liveFinalPrice = computed(() => {
    const value = parseFloat(commissionValue.value);

    if (!commissionType.value || Number.isNaN(value)) {
        return null;
    }

    return commissionType.value === 'percentage'
        ? props.offer.offered_price * (1 + value / 100)
        : props.offer.offered_price + value;
});

const formatMoney = (value, currency) => {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
};

let saveDebounce = null;

const scheduleSave = () => {
    clearTimeout(saveDebounce);
    saveDebounce = setTimeout(save, 600);
};

watch([commissionType, commissionValue], scheduleSave);

const save = async () => {
    // Vue's v-model auto-coerces <input type="number"> to a JS Number once
    // it holds a valid value (no .number modifier needed for that part),
    // but an empty field comes back as '' — so this can't assume either
    // type going in.
    const raw = String(commissionValue.value ?? '').trim();

    saving.value = true;

    try {
        const { data } = await window.axios.patch(route('quote-offers.update', props.offer.id), {
            commission_type: commissionType.value || null,
            commission_value: raw === '' ? null : raw,
        });

        // Adopt the server's authoritative numbers (final_price in
        // particular — always server-calculated, never trusted from here).
        props.offer.commission_type = data.offer.commission_type;
        props.offer.commission_value = data.offer.commission_value;
        props.offer.final_price = data.offer.final_price;
        savedAt.value = Date.now();
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div class="card p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
            <div>
                <p class="text-sm font-medium text-gray-900">
                    {{ offer.operator_name }}
                </p>
                <p class="mt-0.5 text-sm text-gray-600">
                    {{ offer.aircraft_type }}
                    <span v-if="offer.aircraft_registration">— {{ offer.aircraft_registration }}</span>
                    <span v-else class="text-gray-400">— floating fleet, no tail assigned</span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Badge v-if="offer.tail" variant="success">✓ in fleet</Badge>
                <p class="text-sm font-semibold text-gray-900">
                    {{ formatMoney(offer.offered_price, offer.offered_currency) }}
                </p>
            </div>
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm text-gray-600 sm:grid-cols-4">
            <div>
                <dt class="text-gray-500">Year of make</dt>
                <dd class="text-gray-900">{{ offer.year_of_make || '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Max PAX</dt>
                <dd class="text-gray-900">{{ offer.max_pax ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Distance</dt>
                <dd class="text-gray-900">{{ offer.distance_nm ? `${offer.distance_nm} NM` : '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Flight time</dt>
                <dd class="text-gray-900">{{ offer.flight_duration || '—' }}</dd>
            </div>
        </dl>

        <!-- Schedule — local time only, deliberately: the raw email also
             carries a UTC figure next to each of these, which never gets
             extracted in the first place (see AvinodeQuoteEmailParser). -->
        <div v-if="offer.itinerary" class="mt-4 rounded-lg bg-gray-50 p-3 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <p class="text-gray-900">
                    <span class="font-medium">{{ offer.itinerary.departure_time || '—' }}</span>
                    {{ offer.itinerary.departure_airport || '—' }}
                </p>
                <p class="text-gray-400">→</p>
                <p class="text-gray-900">
                    <span class="font-medium">{{ offer.itinerary.arrival_time || '—' }}</span>
                    {{ offer.itinerary.arrival_airport || '—' }}
                </p>
            </div>
            <p class="mt-1 text-xs text-gray-500">
                {{ offer.itinerary.departure_date || '—' }}
                <span v-if="offer.itinerary.pax"> · {{ offer.itinerary.pax }} PAX</span>
                <span v-if="!offer.itinerary.arrival_time"> · arrival time not quoted for this offer</span>
            </p>
        </div>

        <!-- Commission -->
        <div class="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <InputLabel value="Commission" />
                    <select
                        v-model="commissionType"
                        class="mt-1 rounded-lg border-gray-300 py-2 pl-3 pr-8 text-sm text-gray-900 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                    >
                        <option :value="null">None</option>
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed amount</option>
                    </select>
                </div>

                <div v-if="commissionType">
                    <InputLabel
                        :value="commissionType === 'percentage' ? '%' : `Amount (${offer.offered_currency})`"
                    />
                    <input
                        v-model="commissionValue"
                        type="number"
                        min="0"
                        step="0.01"
                        class="mt-1 block w-28 rounded-lg border-gray-300 py-2 text-sm text-gray-900 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                        :placeholder="commissionType === 'percentage' ? 'e.g. 5' : 'e.g. 1500'"
                    />
                </div>

                <p class="text-xs text-gray-400" v-if="saving">Saving…</p>
                <p class="text-xs text-gray-400" v-else-if="savedAt">Saved</p>
            </div>

            <div v-if="liveFinalPrice !== null" class="text-right">
                <p class="text-xs text-gray-500">Total price</p>
                <p class="text-base font-semibold text-gray-900">
                    {{ formatMoney(liveFinalPrice, offer.offered_currency) }}
                </p>
            </div>
        </div>
    </div>
</template>
