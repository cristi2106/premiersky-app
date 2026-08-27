<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    offer: {
        type: Object,
        required: true,
    },
    // Whether the quotation panel above already has a client selected —
    // Generate Contract needs one (a Contract always belongs to a
    // client), so the button stays disabled until then rather than
    // failing after the fact. Owned by the parent (Quotes/Index.vue),
    // same reasoning as applyToAll below: it's quote-wide state, not
    // this card's own.
    hasClient: {
        type: Boolean,
        default: false,
    },
    // "Apply to all" — a quote-wide setting, so it's owned by the parent
    // (Quotes/Index.vue) rather than local state here, and handed to
    // every card so any of them can both drive and receive a sync. Only
    // the card the parent designates (the cheapest offer, i.e. this
    // quote's "Option 1") actually renders the checkbox.
    applyToAll: {
        type: Boolean,
        default: false,
    },
    // The last {type, value} committed by whichever card was edited
    // while applyToAll was on — null until that first happens. Kept as
    // a single shared object (rather than each card reaching into
    // siblings' state) so every card only ever needs to compare against
    // one source of truth.
    sharedCommission: {
        type: Object,
        default: null,
    },
    showApplyToAllCheckbox: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:applyToAll', 'commission-changed']);

// Local editable copies — the offer prop only reflects the last value the
// server confirmed, so typing shouldn't wait on a round-trip to show up.
const commissionType = ref(props.offer.commission_type);
const commissionValue = ref(
    props.offer.commission_value !== null ? String(props.offer.commission_value) : ''
);
const saving = ref(false);
const savedAt = ref(null);
const selected = ref(props.offer.selected);
const savingSelection = ref(false);

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

// Year of make, Max PAX and Flight time collapsed into one line (e.g.
// "2011 · 8 PAX · 03:25") rather than three separate stat columns — the
// list reads as a compact scan of many offers, not one dialog-box-style
// card per offer. Distance isn't part of it; it's dropped entirely, and
// departure/arrival now live only in the shared schedule block above the
// list (see Quotes/Index.vue), since that's identical for every offer on
// the same trip.
const offerStats = computed(() => {
    const parts = [];

    if (props.offer.year_of_make) {
        parts.push(props.offer.year_of_make);
    }

    if (props.offer.max_pax) {
        parts.push(`${props.offer.max_pax} PAX`);
    }

    if (props.offer.flight_duration) {
        parts.push(props.offer.flight_duration);
    }

    return parts.length > 0 ? parts.join(' · ') : '—';
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

// True once this card's own commissionType/commissionValue already equal
// sharedCommission — i.e. the change we're looking at is one that just
// arrived *from* the sync (see the sharedCommission watcher below), not
// one this card originated. Comparing values rather than a "just
// applied" flag sidesteps Vue's watcher batching entirely: there's no
// ordering to get right, a value either matches the broadcast or it
// doesn't.
const matchesSharedCommission = () =>
    props.sharedCommission !== null
    && props.sharedCommission.type === commissionType.value
    && props.sharedCommission.value === commissionValue.value;

watch([commissionType, commissionValue], () => {
    scheduleSave();

    // Re-syncing all on every edit (not just the first) is deliberate —
    // "Apply to all" is a standing rule while it's checked, not a
    // one-time copy, so whichever option you touch next becomes the new
    // value for the rest.
    if (props.applyToAll && !matchesSharedCommission()) {
        emit('commission-changed', { type: commissionType.value, value: commissionValue.value });
    }
});

// Checking the box is itself the "apply now" action — it must broadcast
// whatever this card (the anchor, since only it renders the checkbox)
// already holds at that moment, not wait for a further edit. Without
// this, ticking the box after already typing a commission is a no-op
// until you touch the field again, which is what let stale commissions
// on other options survive a checked "Apply to all": nothing ever told
// them to overwrite. Unconditional — no matchesSharedCommission guard —
// because the whole point is to overwrite every other option's value
// with this one regardless of what they currently hold, including a
// different commission set on a previous visit. Gated to the anchor
// card only: every card receives the same applyToAll prop, so without
// showApplyToAllCheckbox here all of them would fire at once and the
// broadcast would race.
watch(
    () => props.applyToAll,
    (isOn) => {
        if (!isOn || !props.showApplyToAllCheckbox) {
            return;
        }

        emit('commission-changed', { type: commissionType.value, value: commissionValue.value });
    },
);

// Adopts a commission broadcast from whichever option was just edited.
// Skipped once already in sync (see matchesSharedCommission) so this
// can't loop back into re-emitting 'commission-changed' above.
watch(
    () => props.sharedCommission,
    (shared) => {
        if (!props.applyToAll || shared === null || matchesSharedCommission()) {
            return;
        }

        commissionType.value = shared.type;
        commissionValue.value = shared.value;
    },
);

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

// A checkbox toggle is a single deliberate click, not something to
// debounce like the commission text inputs — save it immediately.
const toggleSelected = async () => {
    savingSelection.value = true;

    try {
        const { data } = await window.axios.patch(route('quote-offers.update', props.offer.id), {
            selected: selected.value,
        });

        props.offer.selected = data.offer.selected;
    } finally {
        savingSelection.value = false;
    }
};

// Registered after toggleSelected is defined (const declarations aren't
// hoisted) so the checkbox saves immediately on every toggle — no
// debounce, since a click is already a single deliberate action.
watch(selected, toggleSelected);

// A full page navigation (not axios) — the server always redirects
// somewhere: this offer's own Contract edit page on success, or back
// here with a flash error if it couldn't build one (see
// QuoteOfferController::generateContract()). Guarded by hasClient
// itself (the button is also just disabled) since a plain page
// navigation has no in-flight state to gate a second click on the way
// axios calls elsewhere in this file do.
const generatingContract = ref(false);

const generateContract = () => {
    if (!props.hasClient || generatingContract.value) {
        return;
    }

    generatingContract.value = true;

    router.post(route('quote-offers.generate-contract', props.offer.id), {}, {
        onFinish: () => {
            generatingContract.value = false;
        },
    });
};
</script>

<template>
    <div class="card p-3 sm:p-4" :class="{ 'ring-2 ring-accent-500': selected }">
        <div class="flex items-start gap-3">
            <label class="flex items-center gap-2 pt-0.5" title="Include this offer when generating the client PDF">
                <Checkbox v-model:checked="selected" />
                <span class="sr-only">Include in client PDF</span>
            </label>

            <div>
                <p class="text-sm font-medium text-gray-900">
                    {{ offer.operator_name }}
                </p>
                <p class="mt-0.5 text-sm text-gray-600">
                    {{ offer.aircraft_type }}
                    <span v-if="offer.aircraft_registration">— {{ offer.aircraft_registration }}</span>
                    <span v-else class="text-gray-400">— floating fleet, no tail assigned</span>
                </p>
                <p class="mt-0.5 text-sm text-gray-500">{{ offerStats }}</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">
                    {{ formatMoney(offer.offered_price, offer.offered_currency) }}
                </p>
            </div>
        </div>

        <!-- Commission -->
        <div class="mt-3 flex flex-col gap-3 border-t border-gray-100 pt-3 sm:flex-row sm:items-end sm:justify-between">
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

                <!-- Only this quote's cheapest offer (its "Option 1") shows
                     the toggle — applyToAll itself is quote-wide state
                     owned by the parent page and handed to every card, so
                     wherever it's edited from, the sync still applies to
                     all of them. -->
                <label
                    v-if="showApplyToAllCheckbox"
                    class="flex items-center gap-2 pb-2 text-sm text-gray-600"
                    title="While checked, editing this option's commission (type or value) copies it to every other option in this quote"
                >
                    <Checkbox
                        :checked="applyToAll"
                        @update:checked="$emit('update:applyToAll', $event)"
                    />
                    Apply to all
                </label>

                <!-- Available on every offer regardless of its checkbox
                     above — which offer becomes the client PDF and which
                     becomes the contract are independent choices, so this
                     doesn't read `selected` at all. Only needs a client
                     chosen (a Contract always belongs to one); nothing
                     else here gates it, since aircraft/airport matching
                     is best-effort by design — see
                     QuoteOfferController::generateContract(). -->
                <SecondaryButton
                    type="button"
                    :disabled="!hasClient || generatingContract"
                    :title="hasClient ? undefined : 'Select a client first'"
                    @click="generateContract"
                >
                    {{ generatingContract ? 'Generating…' : 'Generate Contract' }}
                </SecondaryButton>

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
