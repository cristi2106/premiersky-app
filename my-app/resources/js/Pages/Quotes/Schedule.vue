<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ContractLegRow from '@/Components/ContractLegRow.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    quoteRequest: {
        type: Object,
        required: true,
    },
});

const emptyLeg = () => ({
    departure_airport_id: null,
    arrival_airport_id: null,
    flight_date: '',
    departure_time: '',
    pax: 1,
});

const form = useForm({
    tail_id: props.quoteRequest.tail_id,
    legs: props.quoteRequest.legs.length > 0 ? props.quoteRequest.legs.map((leg) => ({ ...leg })) : [emptyLeg()],
});

// The picked Tail's own record — same reasoning as CreateManualQuoteModal:
// this is only ever the source of ContractLegRow's live-calc cruise
// speed (via its own aircraft_speed_reference_id, passed through as
// aircraftId below), never persisted itself. Initialized from the quote's
// existing tail (if any) so the very first render's live-calc already has
// a speed to work from, not just after re-picking one.
const selectedTail = ref(props.quoteRequest.tail);

const tailLabel = (tail) => `${tail.tail} — ${tail.operator}`;

const onTailSelect = (option) => {
    selectedTail.value = option;
};

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

const submit = () => {
    form.put(route('quote-requests.schedule.update', props.quoteRequest.id));
};
</script>

<template>
    <Head :title="`Edit Schedule — ${quoteRequest.reference_label}`" />

    <AdminLayout :title="`Edit Schedule — ${quoteRequest.reference_label}`">
        <form class="max-w-4xl space-y-6" @submit.prevent="submit">
            <div class="card p-6">
                <h2 class="text-sm font-semibold text-gray-900">Reference Aircraft</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Used to calculate flight time below — not tied to any specific offer. Add
                    competing options for operators that respond via "Add Offer" on the quote's own page.
                </p>

                <div class="mt-4 max-w-sm">
                    <InputLabel for="tail" value="Aircraft" />
                    <SearchableSelect
                        id="tail"
                        v-model="form.tail_id"
                        :search-url="route('tails.search')"
                        :option-label="tailLabel"
                        :initial-label="quoteRequest.tail ? tailLabel(quoteRequest.tail) : null"
                        placeholder="Search tail number or operator…"
                        class="mt-1"
                        @select="onTailSelect"
                    >
                        <template #option="{ option }">
                            <span class="text-gray-900">{{ option.tail }}</span>
                            <span class="text-gray-500"> — {{ option.operator }}</span>
                        </template>
                    </SearchableSelect>
                    <InputError class="mt-2" :message="form.errors.tail_id" />
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Legs</h2>
                </div>

                <div class="mt-4 space-y-4">
                    <ContractLegRow
                        v-for="(leg, index) in form.legs"
                        :key="index"
                        v-model="form.legs[index]"
                        :aircraft-id="selectedTail?.aircraft_speed_reference_id ?? null"
                        :leg-number="index + 1"
                        :can-remove="form.legs.length > 1"
                        :errors="legErrors(index)"
                        @remove="removeLeg(index)"
                    />
                </div>

                <SecondaryButton class="mt-4" type="button" @click="addLeg">
                    + Add Leg
                </SecondaryButton>
                <InputError class="mt-2" :message="form.errors.legs" />
            </div>

            <div class="flex items-center justify-end gap-4">
                <Link
                    :href="route('quotes.index', { quote_request_id: quoteRequest.id })"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900"
                >
                    Cancel
                </Link>

                <PrimaryButton :loading="form.processing">
                    Save Schedule
                </PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
