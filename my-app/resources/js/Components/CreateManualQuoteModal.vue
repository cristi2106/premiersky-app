<script setup>
import ContractLegRow from '@/Components/ContractLegRow.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

// One blank leg, in the exact shape ContractLegRow (and the post-creation
// schedule editor) already work with — so the payload we submit lines up
// leg-for-leg with what QuoteRequestController::updateSchedule() already
// accepts. This dialog renders the row with :show-calculation="false", so
// no live distance/flight-time/arrival preview shows while entering a
// leg; the server still calculates and stores all of that on submit (see
// QuoteRequestController::saveLegs()).
const emptyLeg = () => ({
    departure_airport_id: null,
    arrival_airport_id: null,
    flight_date: '',
    departure_time: '',
    pax: 1,
});

const form = useForm({
    client_id: null,
    tail_id: null,
    reference_label: '',
    legs: [emptyLeg()],
});

const clientLabel = (client) => client.company_name || 'Untitled client';

// Same picker AddOfferModal uses — here it's the quote's *reference*
// aircraft rather than one specific offer, purely so
// QuoteRequestController::saveLegs() has a cruise speed to calculate
// every leg's flight time from (see its own doc comment). Independent of
// whatever Tail each competing offer eventually uses once added via
// "Add Offer". Only tail_id is submitted; the calculation happens
// server-side on store, so this dialog needs no client-side speed lookup.
const tailLabel = (tail) => `${tail.tail} — ${tail.operator}`;

const addLeg = () => {
    form.legs.push(emptyLeg());
};

const removeLeg = (index) => {
    if (form.legs.length > 1) {
        form.legs.splice(index, 1);
    }
};

const legErrors = (index) => ({
    departure_airport_id: form.errors[`legs.${index}.departure_airport_id`],
    arrival_airport_id: form.errors[`legs.${index}.arrival_airport_id`],
    flight_date: form.errors[`legs.${index}.flight_date`],
    departure_time: form.errors[`legs.${index}.departure_time`],
    pax: form.errors[`legs.${index}.pax`],
});

// Re-armed every time the modal opens, not just after a successful
// submit — closing via the backdrop/Escape skips submit() entirely, so
// resetting only there would leave a half-filled form (and extra leg
// rows) behind the next time this opens.
watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            form.reset();
            form.clearErrors();
        }
    }
);

const close = () => {
    if (form.processing) {
        return;
    }

    emit('close');
};

const submit = () => {
    form.post(route('quote-requests.store'), {
        // Inertia's router.post() defaults preserveState to true (unlike
        // .get(), which is how viewHistoryItem/refreshHistoryItem below
        // navigate) — without overriding it, the redirect back to
        // Quotes/Index would reuse this same mounted page instance rather
        // than remount it. That leaves every ref that only initializes
        // once from props at setup — clientId chief among them, which is
        // what the "Client-facing quotation" section's client picker
        // reads — stuck on the old (pre-creation) props: no client showing
        // selected despite quote_requests.client_id being saved correctly,
        // until an actual browser refresh forces a fresh mount. Forcing a
        // remount here keeps this navigation consistent with every other
        // "genuinely new quote" transition on this page (see the search
        // history comment above), so it re-initializes from the freshly
        // loaded props same as those do.
        preserveState: false,
        // That remount destroys this modal instance along with the rest of
        // the page before onSuccess below ever runs, so createQuoteModalOpen
        // in the parent is already back to its default (closed) by the time
        // the new page appears — this close() is a harmless, purely
        // defensive no-op kept for the (currently unreachable) case where
        // that stops being true. Cancel/Escape/backdrop still go through
        // close() above.
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="close">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Create Manual Quote</h2>
            <p class="mt-1 text-sm text-gray-600">
                For a trip that didn't come through Avinode/email at all.
            </p>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <div>
                    <InputLabel for="manual_quote_client" value="Client" />
                    <SearchableSelect
                        id="manual_quote_client"
                        v-model="form.client_id"
                        :search-url="route('clients.search')"
                        :option-label="clientLabel"
                        placeholder="Search clients…"
                        class="mt-1"
                    >
                        <template #option="{ option }">
                            <span class="text-gray-900">{{ clientLabel(option) }}</span>
                        </template>
                    </SearchableSelect>
                    <InputError class="mt-2" :message="form.errors.client_id" />
                </div>

                <div>
                    <InputLabel for="manual_quote_tail" value="Aircraft" />
                    <SearchableSelect
                        id="manual_quote_tail"
                        v-model="form.tail_id"
                        :search-url="route('tails.search')"
                        :option-label="tailLabel"
                        placeholder="Search tail number or operator…"
                        class="mt-1"
                    >
                        <template #option="{ option }">
                            <span class="text-gray-900">{{ option.tail }}</span>
                            <span class="text-gray-500"> — {{ option.operator }}</span>
                        </template>
                    </SearchableSelect>
                    <p class="mt-2 text-xs text-gray-500">
                        Calculates each leg's flight time, and is added straight to this quote as its
                        first offer — set its price and commission afterwards on the offer page.
                    </p>
                    <InputError class="mt-2" :message="form.errors.tail_id" />
                </div>

                <div>
                    <h3 class="text-sm font-medium text-gray-700">
                        Legs
                        <span class="text-gray-400">({{ form.legs.length }})</span>
                    </h3>

                    <div class="mt-2 space-y-4">
                        <ContractLegRow
                            v-for="(leg, index) in form.legs"
                            :key="index"
                            v-model="form.legs[index]"
                            :leg-number="index + 1"
                            :can-remove="form.legs.length > 1"
                            :show-calculation="false"
                            :errors="legErrors(index)"
                            @remove="removeLeg(index)"
                        />
                    </div>

                    <SecondaryButton class="mt-4" type="button" @click="addLeg">
                        + Add Leg
                    </SecondaryButton>
                    <InputError class="mt-2" :message="form.errors.legs" />
                </div>

                <div>
                    <InputLabel for="manual_quote_reference_label" value="Reference / label (optional)" />
                    <TextInput
                        id="manual_quote_reference_label"
                        v-model="form.reference_label"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="e.g. Popescu family, or leave blank to auto-generate"
                    />
                    <InputError class="mt-2" :message="form.errors.reference_label" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="close">
                        Cancel
                    </SecondaryButton>

                    <PrimaryButton :loading="form.processing">
                        Create Quote
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
