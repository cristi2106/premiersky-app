<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ContractLegRow from '@/Components/ContractLegRow.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    contract: {
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
    client_id: props.contract.client_id,
    aircraft_speed_reference_id: props.contract.aircraft_speed_reference_id,
    legs: props.contract.legs.length > 0 ? props.contract.legs.map((leg) => ({ ...leg })) : [emptyLeg()],
    price: props.contract.price,
    currency: props.contract.currency,
    vat_percentage: props.contract.vat_percentage,
    special_information: props.contract.special_information ?? '',
    cancellation_policy: props.contract.cancellation_policy ?? '',
    status: props.contract.status,
});

const formatAmount = (amount) =>
    `${amount.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} ${form.currency}`;

const priceWithVat = computed(() => {
    const price = Number(form.price) || 0;
    const vat = Number(form.vat_percentage) || 0;
    return price * (1 + vat / 100);
});

const priceBreakdown = computed(() => {
    const vat = Number(form.vat_percentage) || 0;
    const price = Number(form.price) || 0;

    if (vat <= 0) {
        return formatAmount(price);
    }

    return `${formatAmount(price)} + ${vat}% = ${formatAmount(priceWithVat.value)}`;
});

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

const clientLabel = (client) => client.company_name || 'Untitled client';
const aircraftLabel = (aircraft) => aircraft.type_name;

const submit = () => {
    form.put(route('contracts.update', props.contract.id));
};
</script>

<template>
    <Head :title="`Edit Contract ${contract.reference_number}`" />

    <AdminLayout :title="`Edit Contract — ${contract.reference_number}`">
        <form class="max-w-4xl space-y-6" @submit.prevent="submit">
            <div class="card p-6">
                <h2 class="text-sm font-semibold text-gray-900">Contract Details</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="client" value="Client" />
                        <SearchableSelect
                            id="client"
                            v-model="form.client_id"
                            :search-url="route('clients.search')"
                            :option-label="clientLabel"
                            :initial-label="contract.client ? clientLabel(contract.client) : null"
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
                        <InputLabel for="aircraft_type" value="Aircraft Type" />
                        <SearchableSelect
                            id="aircraft_type"
                            v-model="form.aircraft_speed_reference_id"
                            :search-url="route('aircraft-speed-references.search')"
                            :option-label="aircraftLabel"
                            :initial-label="contract.aircraft ? contract.aircraft.type_name : null"
                            placeholder="Search aircraft type…"
                            class="mt-1"
                        >
                            <template #option="{ option }">
                                <span class="text-gray-900">{{ option.type_name }}</span>
                            </template>
                        </SearchableSelect>
                        <p class="mt-2 text-xs text-gray-500">Applies to every leg on this contract.</p>
                        <InputError class="mt-2" :message="form.errors.aircraft_speed_reference_id" />
                    </div>

                    <div>
                        <InputLabel for="status" value="Status" />
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                        >
                            <option value="draft">Draft</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.status" />
                    </div>
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
                        :aircraft-id="form.aircraft_speed_reference_id"
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

            <div class="card p-6">
                <h2 class="text-sm font-semibold text-gray-900">Price</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="price" value="Price" />
                        <TextInput
                            id="price"
                            v-model="form.price"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.price" />
                    </div>

                    <div>
                        <InputLabel for="currency" value="Currency" />
                        <select
                            id="currency"
                            v-model="form.currency"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                        >
                            <option value="EUR">EUR</option>
                            <option value="RON">RON</option>
                            <option value="USD">USD</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.currency" />
                    </div>

                    <div>
                        <InputLabel for="vat_percentage" value="VAT (%)" />
                        <TextInput
                            id="vat_percentage"
                            v-model="form.vat_percentage"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.vat_percentage" />
                    </div>

                    <div class="flex items-end">
                        <p class="text-sm text-gray-700">{{ priceBreakdown }}</p>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="text-sm font-semibold text-gray-900">Additional Information</h2>

                <div class="mt-4 space-y-6">
                    <div>
                        <InputLabel for="special_information" value="Special Information" />
                        <p class="mt-1 text-xs text-gray-500">Optional notes about this booking, shown on the PDF.</p>
                        <textarea
                            id="special_information"
                            v-model="form.special_information"
                            rows="3"
                            class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                        />
                        <InputError class="mt-2" :message="form.errors.special_information" />
                    </div>

                    <div>
                        <InputLabel for="cancellation_policy" value="Cancellation Policy" />
                        <p class="mt-1 text-xs text-gray-500">Optional — shown on the PDF after Special Information.</p>
                        <textarea
                            id="cancellation_policy"
                            v-model="form.cancellation_policy"
                            rows="3"
                            class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 placeholder-gray-400 shadow-sm focus:border-accent-500 focus:ring-accent-500"
                        />
                        <InputError class="mt-2" :message="form.errors.cancellation_policy" />
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-4">
                <Link
                    :href="route('contracts.index')"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900"
                >
                    Cancel
                </Link>

                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Save Contract
                </PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
