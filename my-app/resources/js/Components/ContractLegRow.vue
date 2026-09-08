<script setup>
import { computed, ref, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import TextInput from '@/Components/TextInput.vue';
import TimeInput from '@/Components/TimeInput.vue';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    aircraftId: {
        type: [Number, String],
        default: null,
    },
    legNumber: {
        type: Number,
        required: true,
    },
    canRemove: {
        type: Boolean,
        default: true,
    },
    // When false, the live-calc panel (distance / flight time / arrival)
    // is hidden and the calc request is skipped entirely. Used by the
    // Create Manual Quote dialog, which only collects the leg inputs and
    // leaves the calculation to the server on store
    // (QuoteRequestController::saveLegs()). Every other caller leaves this
    // on to show the running preview as fields are filled.
    showCalculation: {
        type: Boolean,
        default: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['update:modelValue', 'remove']);

const field = (key) =>
    computed({
        get: () => props.modelValue[key],
        set: (value) => emit('update:modelValue', { ...props.modelValue, [key]: value }),
    });

const departureAirportId = field('departure_airport_id');
const arrivalAirportId = field('arrival_airport_id');
const flightDate = field('flight_date');
const departureTime = field('departure_time');
const pax = field('pax');

const result = ref(null);
const calculating = ref(false);
const errorMessage = ref('');

const sameAirportSelected = computed(
    () =>
        departureAirportId.value !== null &&
        arrivalAirportId.value !== null &&
        departureAirportId.value === arrivalAirportId.value
);

const readyToCalculate = computed(
    () =>
        departureAirportId.value !== null &&
        arrivalAirportId.value !== null &&
        props.aircraftId !== null &&
        flightDate.value !== '' &&
        departureTime.value !== '' &&
        !sameAirportSelected.value
);

let debounceTimer = null;

const runCalculation = async () => {
    calculating.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.post(route('flight-calculator.calculate'), {
            departure_airport_id: departureAirportId.value,
            arrival_airport_id: arrivalAirportId.value,
            aircraft_speed_reference_id: props.aircraftId,
            departure_date: flightDate.value,
            departure_time: departureTime.value,
        });

        result.value = response.data;
    } catch (error) {
        result.value = null;
        errorMessage.value =
            error.response?.data?.message ?? 'Could not calculate this leg. Please check the fields above.';
    } finally {
        calculating.value = false;
    }
};

const airportLabel = (airport) =>
    `${airport.name} (${airport.icao_code}${airport.iata_code ? '/' + airport.iata_code : ''})`;

watch(
    [departureAirportId, arrivalAirportId, flightDate, departureTime, () => props.aircraftId],
    () => {
        result.value = null;
        errorMessage.value = '';
        clearTimeout(debounceTimer);

        if (!props.showCalculation || !readyToCalculate.value) {
            return;
        }

        debounceTimer = setTimeout(runCalculation, 250);
    },
    { immediate: true }
);
</script>

<template>
    <div class="card p-5">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Leg {{ legNumber }}</h3>

            <button
                v-if="canRemove"
                type="button"
                class="-my-1 cursor-pointer rounded-lg px-2 py-1 text-sm font-medium text-red-600 transition duration-150 ease-in-out hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                @click="emit('remove')"
            >
                Remove
            </button>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <InputLabel :for="`leg-${legNumber}-departure-airport`" value="Departure Airport" />
                <SearchableSelect
                    :id="`leg-${legNumber}-departure-airport`"
                    v-model="departureAirportId"
                    :search-url="route('airports.search')"
                    :option-label="airportLabel"
                    :initial-label="modelValue.departure_airport ? airportLabel(modelValue.departure_airport) : null"
                    placeholder="Search by name, ICAO or IATA…"
                    class="mt-1"
                >
                    <template #option="{ option }">
                        <span class="font-medium text-gray-900">{{ option.name }}</span>
                        <span class="ml-1 text-gray-500"
                            >({{ option.icao_code }}{{ option.iata_code ? '/' + option.iata_code : '' }})</span
                        >
                    </template>
                </SearchableSelect>
                <InputError class="mt-2" :message="errors.departure_airport_id" />
            </div>

            <div>
                <InputLabel :for="`leg-${legNumber}-arrival-airport`" value="Arrival Airport" />
                <SearchableSelect
                    :id="`leg-${legNumber}-arrival-airport`"
                    v-model="arrivalAirportId"
                    :search-url="route('airports.search')"
                    :option-label="airportLabel"
                    :initial-label="modelValue.arrival_airport ? airportLabel(modelValue.arrival_airport) : null"
                    placeholder="Search by name, ICAO or IATA…"
                    class="mt-1"
                >
                    <template #option="{ option }">
                        <span class="font-medium text-gray-900">{{ option.name }}</span>
                        <span class="ml-1 text-gray-500"
                            >({{ option.icao_code }}{{ option.iata_code ? '/' + option.iata_code : '' }})</span
                        >
                    </template>
                </SearchableSelect>
                <p v-if="sameAirportSelected" class="mt-2 text-sm text-red-600">
                    Departure and arrival airports must be different.
                </p>
                <InputError v-else class="mt-2" :message="errors.arrival_airport_id" />
            </div>

            <div>
                <InputLabel :for="`leg-${legNumber}-date`" value="Flight Date" />
                <TextInput
                    :id="`leg-${legNumber}-date`"
                    v-model="flightDate"
                    type="date"
                    class="mt-1 block w-full"
                />
                <InputError class="mt-2" :message="errors.flight_date" />
            </div>

            <div>
                <InputLabel :for="`leg-${legNumber}-time`" value="Departure Time (local)" />
                <TimeInput
                    :id="`leg-${legNumber}-time`"
                    v-model="departureTime"
                    class="mt-1 block w-full"
                />
                <InputError class="mt-2" :message="errors.departure_time" />
            </div>

            <div>
                <InputLabel :for="`leg-${legNumber}-pax`" value="Passengers" />
                <TextInput
                    :id="`leg-${legNumber}-pax`"
                    v-model="pax"
                    type="number"
                    min="1"
                    class="mt-1 block w-full"
                />
                <InputError class="mt-2" :message="errors.pax" />
            </div>
        </div>

        <div v-if="showCalculation" class="mt-4 rounded-lg bg-gray-50 p-4">
            <div v-if="errorMessage" class="text-sm text-red-600">
                {{ errorMessage }}
            </div>

            <div v-else-if="calculating" class="text-sm text-gray-500">Calculating…</div>

            <div v-else-if="result" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Distance</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">
                        {{ result.distance_nautical_miles.toLocaleString() }} nm
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Flight Time</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">
                        {{ result.duration_formatted }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Departure (local)</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">
                        {{ result.departure.formatted }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Arrival (local)</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">
                        {{ result.arrival.formatted }}
                    </p>
                </div>
            </div>

            <div v-else class="text-sm text-gray-500">
                Fill in both airports, the flight date and departure time to see this leg's flight time.
            </div>
        </div>
    </div>
</template>
